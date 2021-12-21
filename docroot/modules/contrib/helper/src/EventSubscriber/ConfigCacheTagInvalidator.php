<?php

namespace Drupal\helper\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber for invalidating cache tags when a config object is saved.
 *
 * @code
 * # In mymodule.services.yml:
 * services:
 *   mymodule.settings_cache_subscriber:
 *     class: Drupal\helper\EventSubscriber\ConfigSaveCacheTagInvalidator
 *     arguments: ['@cache_tags.invalidator', 'mymodule.settings', ['cache_tag_1', 'cache_tag_2']]
 *     tags:
 *     - { name: event_subscriber }
 * @endcode
 */
class ConfigCacheTagInvalidator implements EventSubscriberInterface {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The configuration object name.
   *
   * @var string
   */
  protected $configName;

  /**
   * The cache tags to invalidate when the configuration object is changed.
   *
   * @var array
   */
  protected $cacheTags;

  /**
   * Constructs a ConfigCacheTagInvalidator object.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param string $config_name
   *   The configuration object name.
   * @param array $cache_tags
   *   The cache tags to invalidate when the configuration object is changed.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator, string $config_name, array $cache_tags) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->configName = $config_name;
    $this->cacheTags = $cache_tags;
  }

  /**
   * Invalidate the cache tags whenever the config is changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event to process.
   */
  public function onChange(ConfigCrudEvent $event) {
    if ($event->getConfig()->getName() === $this->configName) {
      $this->cacheTagsInvalidator->invalidateTags($this->cacheTags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onChange'];
    $events[ConfigEvents::DELETE][] = ['onChange'];
    return $events;
  }

}
