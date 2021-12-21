<?php

namespace Drupal\helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Installer\Exception\InstallerException;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Helpers related to working with install profiles.
 */
class InstallProfile {

  use StringTranslationTrait;

  /**
   * The profile extension list service.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected $profileList;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The module schema key/value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $schemaKeyValue;

  /**
   * InstallProfileSwitcher constructor.
   *
   * @param \Drupal\Core\Extension\ExtensionList $profileList
   *   The profile extension list service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key/value storage factory.
   */
  public function __construct(ExtensionList $profileList, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $module_handler, KeyValueFactoryInterface $key_value_factory) {
    $this->profileList = $profileList;
    $this->configFactory = $configFactory;
    $this->moduleHandler = $module_handler;
    $this->schemaKeyValue = $key_value_factory->get('system.schema');
  }

  /**
   * Switches the site's install profile.
   *
   * Note the following:
   * - This does not run the new profile's install hooks.
   * - Does not enable any of the profile's dependencies.
   * - This does not check that currently enabled modules are located in the
   *   current profile's code and will no longer be available once switching
   *   to the new profile.
   * - Does not run any uninstallation of the current profile, like removing
   *   any tables defined in hook_schema().
   *
   * @param string $profile
   *   The machine name of the profile to switch to.
   * @param int $schema_version
   *   The schema version to set for the install profile.
   *
   * @author https://www.drupal.org/project/profile_switcher
   */
  public function switch($profile, $schema_version = NULL) {
    $current_profile = \Drupal::installProfile();

    // Forces ExtensionDiscovery to rerun for profiles.
    // @see https://www.drupal.org/project/drupal/issues/3240928
    $this->profileList->reset();

    // Set the profile in configuration.
    $extension_config = $this->configFactory->getEditable('core.extension');
    $extension_config->set('profile', $profile);
    $extension_config->save();

    drupal_flush_all_caches();

    // Install profiles are also registered as enabled modules.
    // Remove the old profile and add in the new one.
    $extension_config->clear("module.{$current_profile}");
    // The install profile is always given a weight of 1000 by the core
    // extension system.
    $extension_config->set("module.$profile", 1000);
    $extension_config->save();

    // Clear caches again.
    drupal_flush_all_caches();

    // Find the correct schema version of the install profile to set as the
    // current schema version. Assume use the latest schema version if a value
    // has not been provided.
    $schema_version = $schema_version ?: $this->getLatestSchemaVersion($profile);

    // Remove the schema value for the old install profile, and set the schema
    // for the new one.
    $this->schemaKeyValue->delete($current_profile);
    $this->schemaKeyValue->set($profile, $schema_version);

    // Clear caches again.
    drupal_flush_all_caches();
  }

  /**
   * Validates a profile and checks its various requirements.
   *
   * This method performs the following validations:
   * - Does the profile exist?
   * - Does the profile have all of its module dependencies currently enabled?
   * - Does the profile or any of its dependencies fail any hook_requirements()
   *   checks with errors?
   * - Does the current profile have any enabled modules or themes inside of it
   *   that will go "missing" when switching to the new profile?
   *
   * @param string $profile
   *   The profile name.
   *
   * @throws \InvalidArgumentException
   *   If the profile does not exist.
   * @throws \Drupal\Core\Installer\Exception\InstallerException
   *   If the profile failed any validations.
   */
  public function validateProfile($profile) {
    // Forces ExtensionDiscovery to rerun for profiles.
    // @see https://www.drupal.org/project/drupal/issues/3240928
    $this->profileList->reset();

    // Ensure the profile exists.
    if (!$this->profileList->exists($profile)) {
      throw new \InvalidArgumentException("The {$profile} profile does not exist.");
    }

    // Ensure that the desired profile is different from the current profile.
    if ($profile === \Drupal::installProfile()) {
      throw new \InvalidArgumentException("The current install profile is already set to {$profile}.");
    }

    // Make sure the installation API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    // Check that the profile's dependencies are already enabled.
    $info = install_profile_info($profile);
    if (!empty($info['dependencies']) && $missing_dependencies = array_diff($info['dependencies'], array_keys($this->moduleHandler->getModuleList()))) {
      sort($missing_dependencies);
      throw new InstallerException("The following module dependencies are not enabled or are missing for the {$profile} profile: " . implode(', ', $missing_dependencies) . '.');
    }

    // Ensure that the profile's .install file and classes are available, since
    // drupal_check_profile only does this for the install profile's
    // dependencies, but not the install profile itself.
    $profile_path = drupal_get_path('profile', $profile);
    $profile_install_file = DRUPAL_ROOT . '/' . $profile_path . "/$profile.install";
    \Drupal::service('class_loader')->addPsr4('Drupal\\' . $profile_path . '\\', \Drupal::root() . "/$profile_path/src");
    if (is_file($profile_install_file)) {
      require_once $profile_install_file;
    }

    // Ensure that requirements are checked for the new profile.
    $requirements = drupal_check_profile($profile);
    if (is_array($requirements) && drupal_requirements_severity($requirements) === REQUIREMENT_ERROR) {
      $reasons = $this->formatRequirementsReasons($requirements);
      throw new InstallerException("Failed requirements check for the {$profile} profile:\n" . implode("\n", $reasons));
    }

    // Check for any modules or themes inside the profile that are enabled and
    // may not be available in the new profile.
    $this->checkEnabledProfileExtensions($profile, 'module');
    $this->checkEnabledProfileExtensions($profile, 'theme');
  }

  /**
   * Gets the latest schema version for an install profile.
   *
   * @param string $profile
   *   The install profile name.
   *
   * @return int
   *   The schema version.
   */
  protected function getLatestSchemaVersion($profile) {
    module_load_install($profile);
    $versions = drupal_get_schema_versions($profile) ?? [];
    $versions[] = $this->moduleHandler->invoke($profile, 'update_last_removed') ?? \Drupal::CORE_MINIMUM_SCHEMA_VERSION;
    return max($versions);
  }

  /**
   * Convert an array of requirements into a simpler list of reasons.
   *
   * @param array $requirements
   *   An array of requirements from hook_requirements().
   *
   * @return string[]
   *   An array of requirement reasons as strings.
   */
  protected function formatRequirementsReasons(array $requirements) {
    $reasons = [];
    foreach ($requirements as $id => $requirement) {
      if (isset($requirement['severity']) && $requirement['severity'] === REQUIREMENT_ERROR) {
        if (isset($requirement['value']) && $requirement['value']) {
          $reasons[$id] = $this->t('@requirements_message (Currently using @item version @version)', [
            '@requirements_message' => $requirement['description'],
            '@item' => $requirement['title'],
            '@version' => $requirement['value'],
          ]);
        }
        else {
          $reasons[$id] = (string) $requirement['description'];
        }
      }
    }
    return $reasons;
  }

  /**
   * Check for any enabled extensions that reside inside the current profile.
   *
   * @param string $profile
   *   The new desired profile.
   * @param string $type
   *   The extension type. Possible values are module or theme.
   *
   * @throws \Drupal\Core\Installer\Exception\InstallerException
   *   If there are extensions that enabled in the currnet profile that do not
   *   also reside in the new profile.
   */
  protected function checkEnabledProfileExtensions($profile, $type) {
    $current_profile = \Drupal::installProfile();
    $current_profile_path = $this->profileList->getPath($current_profile);
    $new_profile_path = $this->profileList->getPath($profile);

    /** @var \Drupal\Core\Extension\ExtensionList $extensionList */
    $extensionList = \Drupal::service('extension.list.' . $type);
    $extensions = array_keys($extensionList->getAllInstalledInfo());

    $missing_extensions = [];
    foreach ($extensions as $extension) {
      // Skip the current profile (which also acts as a module) since it is not
      // relevant.
      if ($type === 'module' && $extension === $current_profile) {
        continue;
      }

      // If the extension lives inside the current profile but cannot be found
      // in the new profile, then add it to the list.
      // @todo Convert this to use ExtensionDiscovery using the new profile path like drupal_check_profile() does.
      if (strpos($extensionList->getPath($extension), $current_profile_path) !== FALSE && !is_dir($new_profile_path . '/' . $type . '/' . $extension)) {
        $missing_extensions[] = $extension;
      }
    }

    // Throw an exception if there will be any missing extensions.
    if (!empty($missing_extensions)) {
      sort($missing_extensions);
      throw new InstallerException("The following {$type}s are located inside the current {$current_profile} profile and may not be available when switching to the {$profile} profile: " . implode(', ', $missing_extensions) . ".");
    }
  }

}
