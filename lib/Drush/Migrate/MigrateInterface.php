<?php

/**
 * @file
 * Contains \Drush\Migrate\MigrateInterface.
 */

namespace Drush\Migrate;

interface MigrateInterface {

  /**
   * Run all specified migrations.
   */
  public function import();

}
