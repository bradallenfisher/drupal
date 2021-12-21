<?php

namespace Drupal\helper\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber class that does nothing. Use for service overrides.
 */
class NullEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [];
  }

}
