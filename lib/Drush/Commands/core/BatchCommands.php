<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class BatchCommands extends DrushCommands {

  /**
   * Process operations in the specified batch set.
   *
   * Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
   *
   * @command batch-process
   * @param $batch_id The batch id that will be processed.
   * @hidden
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_LOGIN
   */
  public function process($batch_id) {
    drush_batch_command($batch_id);
  }

  /**
   * Perform update functions.
   *
   * @command updatedb-batch-process
   * @param $batch_id The batch id that will be processed.
   * @hidden
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function updatedb_process($batch_id) {
    drush_include_engine('drupal', 'update');
    _update_batch_command($id);
  }

}
