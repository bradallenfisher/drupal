<?php

namespace Drupal\Tests\helper\Kernel;

use Drupal\Core\Installer\Exception\InstallerException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the install profile helper.
 *
 * @coversDefaultClass \Drupal\helper\InstallProfile
 * @group helper
 */
class InstallProfileTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'file',
    'helper',
  ];

  /**
   * The install profile helper.
   *
   * @var \Drupal\helper\InstallProfile
   */
  protected $installProfile;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installProfile = $this->container->get('helper.install_profile');
    $this->setInstallProfile('testing');
  }

  /**
   * Tests switching profiles.
   *
   * @covers ::switch
   */
  public function testSwitch() {
    $this->assertInstallProfile('testing');
    $this->assertSame(SCHEMA_UNINSTALLED, drupal_get_installed_schema_version('minimal'));
    $this->assertSame(SCHEMA_UNINSTALLED, drupal_get_installed_schema_version('standard'));

    $this->installProfile->switch('minimal');
    $this->assertInstallProfile('minimal', 8000);
    $this->assertSame(SCHEMA_UNINSTALLED, drupal_get_installed_schema_version('standard'));
    $this->assertSame(SCHEMA_UNINSTALLED, drupal_get_installed_schema_version('testing'));
  }

  /**
   * Tests switching to an invalid profile should fail.
   *
   * @covers ::validateProfile
   */
  public function testProfileDoesNotExist() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The profile_does_not_exist profile does not exist.');
    $this->installProfile->validateProfile('profile_does_not_exist');
  }

  /**
   * Tests switching to the same profile should fail.
   *
   * @covers ::validateProfile
   */
  public function testCurrentProfile() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The current install profile is already set to testing.');
    $this->installProfile->validateProfile('testing');
  }

  /**
   * Tests switching to a profile with failed requirements.
   *
   * @covers ::validateProfile
   * @covers ::formatRequirementsReasons
   */
  public function testRequirements() {
    $this->expectException(InstallerException::class);
    $this->expectExceptionMessage("Failed requirements check for the testing_requirements profile:\nTesting requirements failed requirements.");
    $this->installProfile->validateProfile('testing_requirements');
  }

  /**
   * Tests switching to a profile with missing dependencies.
   *
   * @covers ::validateProfile
   */
  public function testMissingDependencies() {
    // The testing profile should not have the ban nor dblog modules enabled.
    $this->expectException(InstallerException::class);
    $this->expectExceptionMessage("The following module dependencies are not enabled or are missing for the testing_install_profile_all_dependencies profile: ban, dblog.");
    $this->installProfile->validateProfile('testing_install_profile_all_dependencies');
  }

  /**
   * Tests switching from a profile which has an enabled module inside it.
   *
   * @covers ::validateProfile
   * @covers ::checkEnabledProfileExtensions
   */
  public function testEnabledModuleInProfile() {
    $this->enableModules(['drupal_system_cross_profile_test', 'drupal_system_listing_compatible_test']);
    $this->expectException(InstallerException::class);
    $this->expectExceptionMessage("The following modules are located inside the current testing profile and may not be available when switching to the minimal profile: drupal_system_cross_profile_test, drupal_system_listing_compatible_test.");
    $this->installProfile->validateProfile('minimal');
  }

  /**
   * Tests switching from a profile which has an enabled theme inside it.
   *
   * @covers ::validateProfile
   * @covers ::checkEnabledProfileExtensions
   */
  public function testEnabledThemeInProfile() {
    $this->setInstallProfile('demo_umami');
    $this->container->get('theme_installer')->install(['umami']);
    $this->expectException(InstallerException::class);
    $this->expectExceptionMessage("The following themes are located inside the current demo_umami profile and may not be available when switching to the minimal profile: umami.");
    $this->installProfile->validateProfile('minimal');
  }

  /**
   * Asserts the currently installed profile.
   *
   * @param string $expected_profile
   *   The expected install profile.
   * @param int $expected_schema_version
   *   (optional) The expected schema version for the install profile.
   */
  protected function assertInstallProfile($expected_profile, $expected_schema_version = NULL) {
    $this->assertSame($expected_profile, \Drupal::installProfile());
    if (isset($expected_schema_version)) {
      $this->assertSame($expected_schema_version, drupal_get_installed_schema_version($expected_profile));
    }
  }

}
