<?php

/**
 * @file
 * Contains \Drush\Migrate\MigrateInterface.
 */

namespace Drush\Migrate;

interface MigrateInterface {

  /**
   * Run all specified migrations.
   *
   * @return array
   *   An array of completed migrations.
   */
  public function import();

}
