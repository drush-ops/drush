<?php

namespace Drush\Drupal;

use Drupal\Core\Extension\ExtensionDiscovery as DrupalExtensionDiscovery;

class ExtensionDiscovery extends DrupalExtensionDiscovery {
  static public function reset() {
    static::$files = array();
  }
}

