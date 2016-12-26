<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Log\LogLevel;


class UpdateDBCommands extends DrushCommands {

  /**
   * Apply any database updates required (as with running update.php).
   *
   * @command updatedb
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @option entity-updates Run automatic entity schema updates at the end of any update hooks. Defaults to --no-entity-updates.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_SITE
   * @aliases updb
   */
  public function updatedb($options = ['cache-clear' => TRUE]) {
    if (drush_get_context('DRUSH_SIMULATE')) {
      $this->logger()->info(dt('updatedb command does not support --simulate option.'));
      return TRUE;
    }

    drush_include_engine('drupal', 'update');
    $result = update_main();
    if ($result === FALSE) {
      throw new \Exception('Database updates not complete.');
    }
    elseif ($result > 0) {
      // Clear all caches in a new process. We just performed major surgery.
      drush_drupal_cache_clear_all();

      $this->logger()->log(LogLevel::SUCCESS, dt('Finished performing updates.'));
    }
  }

  /**
   * Apply pending entity schema updates.
   *
   * @command entity-updates
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases entup
   *
   */
  public function entity_updates($options = ['cache-clear' => TRUE]) {
    if (drush_get_context('DRUSH_SIMULATE')) {
      $this->logger()->info(dt('entity-updates command does not support --simulate option.'));
    }

    drush_include_engine('drupal', 'update');
    if (entity_updates_main() === FALSE) {
      throw new \Exception('Entity updates not run.');
    }

    drush_drupal_cache_clear_all();

    $this->logger()->log(LogLevel::SUCCESS, dt('Finished performing updates.'));
  }

  /**
   * List any pending database updates.
   *
   * @command updatedb-status
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @option entity-updates Run automatic entity schema updates at the end of any update hooks. Defaults to --no-entity-updates.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_SITE
   * @aliases updbst
   * @field-labels
   *   module: Module
   *   update_id: Update ID
   *   description: Description
   * @default-fields module,update_id,description
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function updatedb_status($options = ['format'=> 'table']) {
    require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
    drupal_load_updates();
    drush_include_engine('drupal', 'update');
    list($pending, $start) = updatedb_status();
    if (empty($pending)) {
      $this->logger()->info(dt("No database updates required"));
    }
    return new RowsOfFields($pending);
  }

}