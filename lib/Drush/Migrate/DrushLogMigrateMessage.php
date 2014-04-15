<?php
/**
 * @file
 * Contains Drush\Migrate\DrushLogMigrateMessage.
 */

namespace Drush\Migrate;

use Drupal\migrate\MigrateMessageInterface;

class DrushLogMigrateMessage implements MigrateMessageInterface {
  public function display($message, $type = 'status') {
    drush_log($message, $type);
  }
}
