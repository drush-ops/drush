<?php

/**
 * @file
 * Contains Drush\Migrate\DrushLogMigrateMessage.
 */

namespace Drush\Migrate;

use Drupal\migrate\MigrateMessageInterface;

class DrushLogMigrateMessage implements MigrateMessageInterface {

  /**
   * Output a message from the migration.
   *
   * @param string $message
   *   The message to display.
   * @param string $type
   *   The type of message to display.
   *
   * @see drush_log()
   */
  public function display($message, $type = 'status') {
    drush_log($message, $type);
  }

}
