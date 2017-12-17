<?php

namespace Drush\Drupal;

use Drupal\Core\Extension\ExtensionDiscovery as DrupalExtensionDiscovery;

class ExtensionDiscovery extends DrupalExtensionDiscovery
{
    public static function reset()
    {
        static::$files = [];
    }
}
