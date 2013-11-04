<?php

namespace Drupal\Drush;

class Version
{
  const VERSION = '7.0.0-dev';

  public static function compare($version)
  {
    $currentVersion = str_replace(' ', '', strtolower(self::VERSION));
    $version        = str_replace(' ', '', $version);

    return version_compare($version, $currentVersion);
  }
}
