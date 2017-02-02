<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;

/**
 * This class is a good example of how to build a sql-sanitize extension.
 */
class SanitizeSessionsCommands extends DrushCommands {

  /**
   * Sanitize sessions from the DB. This also an example of how to write a
   * database sanitizer for sql-sync.
   *
   * @param $result Exit code from the main operation for this command.
   * @param $commandData Information about the current request.
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    Database::getConnection()->truncate('sessions')->execute();
    $this->logger()->success(dt('Sessions table truncated.'));
  }

  /**
   * @hook on-event sql-sanitize-confirms
   * @param $messages An array of messages to show during confirmation.
   * @param $options The effective commandline options for this request.
   */
  public function messages(&$messages, $options) {
    $messages[] = dt('Truncate sessions table.');
  }
}

