<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;

/**
 * This class is a good example of how to build a sql-sanitize extension.
 */
class SanitizeUserFieldsCommands extends DrushCommands {

  /**
   * Sanitize string fields associated with the user.
   *
   * @todo Use Drupal services to get field info.
   *
   * @param $result Exit code from the main operation for this command.
   * @param $commandData Information about the current request.
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    $options = $commandData->options();
    $conn = Database::getConnection();
    $sql_class = drush_sql_get_class();
    $tables = $sql_class->listTables();
    $whitelist_fields = (array) explode(',', $options['whitelist-fields']);

    foreach ($tables as $table) {
      if (strpos($table, 'user__field_') === 0) {
        $field_name = substr($table, 6, strlen($table));
        if (in_array($field_name, $whitelist_fields)) {
          continue;
        }

        $conditions = [':name' => $field_name];
        $output = $conn->select('SELECT data FROM config WHERE name = field.field.user.user.:name', NULL, $conditions);
        $field_config = unserialize($output[0]);
        $field_type = $field_config['field_type'];
        $randomizer = new Random();

        switch ($field_type) {

          case 'email':
            $conn->update($table)->fields([$field_name . '_value' => $randomizer->name(10) . '@example.com']);
            break;

          case 'string':
            $conn->update($table)->fields([$field_name . '_value' => $randomizer->name(255)]);
            break;

          case 'string_long':
            $conn->update($table)->fields([$field_name . '_value' => $randomizer->sentences(1)]);
            break;

          case 'telephone':
            $conn->update($table)->fields([$field_name . '_value' => '15555555555']);
            break;

          case 'text':
            $conn->update($table)->fields([$field_name . '_value' => $randomizer->paragraphs(2)]);
            break;

          case 'text_long':
            $conn->update($table)->fields([$field_name . '_value' => $randomizer->paragraphs(10)]);
            break;

          case 'text_with_summary':
            $conn->update($table)->fields([$field_name . '_value' => $randomizer->paragraphs(2)]);
            $conn->update($table)->fields([$field_name . '_summary' => $randomizer->name(255)]);
            break;
        }
        $this->logger()->success(dt('!table sanitized.', ['!table' => $table]));
      }
    }
  }

  /**
   * @hook on-event sql-sanitize-confirms
   * @param $messages An array of messages to show during confirmation.
   * @param $options The effective commandline options for this request.
   */
  public function messages(&$messages, $options) {
    $messages[] = dt('Sanitize string fields associated with the user.');
  }
}