<?php

declare(strict_types=1);

namespace Drush\Drupal;

use Drupal\Core\Extension\ExtensionDiscovery as DrupalExtensionDiscovery;

class ExtensionDiscovery extends DrupalExtensionDiscovery
{
    public static function reset(): void
    {
        static::$files = [];
    }
}
