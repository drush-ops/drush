<?php

namespace Drupal\woot\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigImportValidateEventSubscriberBase;

/**
 * Subscribes to Symfony events and maps them to Rules events.
 */
class ConfigSubscriber extends ConfigImportValidateEventSubscriberBase
{

  /**
   * {@inheritdoc}
   */
    public static function getSubscribedEvents()
    {
        $events = [];

        // In this example, we would use information from the State API to determine
        // what events we should subscribe to. Suffice it to say we trust that the
        // State API works correctly, so we're only going to check if the service is
        // available here to make our point.
        if (\Drupal::hasService('state')) {
            $events[ConfigEvents::IMPORT_VALIDATE][] = 'onConfigImporterValidate';
        }

        return $events;
    }

  /**
   * {@inheritdoc}
   */
    public function onConfigImporterValidate(ConfigImporterEvent $event)
    {
        // Always log an error.
        $importer = $event->getConfigImporter();
        $importer->logError($this->t('woot config error'));
    }
}
