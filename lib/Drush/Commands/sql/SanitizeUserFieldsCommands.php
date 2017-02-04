<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of how to build a sql-sanitize plugin.
 */
class SanitizeUserFieldsCommands extends DrushCommands implements SqlSanitizePluginInterface {

  /**
   * Sanitize string fields associated with the user.
   *
   * @todo Use Drupal services to get field info.
   *
   * @hook post-command sql-sanitize
   *
   * @inheritdoc
   */
  public function sanitize($result, CommandData $commandData) {
    $options = $commandData->options();
    $randomizer = new Random();
    $conn = Database::getConnection();
    $field_definitions = \Drupal::entityManager()->getFieldDefinitions('user', 'user');
    $field_storage = \Drupal::entityManager()->getFieldStorageDefinitions('user');
    foreach (explode(',', $options['whitelist-fields']) as $key => $def) {
      unset($field_definitions[$key], $field_storage[$key]);
    }

    foreach ($field_definitions as $key => $def) {
      $execute = FALSE;
      if ($field_storage[$key]->isBaseField()) {
        continue;
      }

      $table = 'user__' . $key;
      $query = $conn->update($table);
      $name = $def->getName();
      // @todo
      // $value = StringItem::generateSampleValue($def);
      switch ($def->getType()) {
        case 'email':
          $query->fields([$name . '_value' => $randomizer->name(10) . '@example.com']);
          $execute = TRUE;
          break;
        case 'string':
          $query->fields([$name . '_value' => $randomizer->name(250)]);
          $execute = TRUE;
          break;

        case 'string_long':
          $query->fields([$name . '_value' => $randomizer->sentences(1)]);
          $execute = TRUE;
          break;

        case 'telephone':
          $query->fields([$name . '_value' => '15555555555']);
          $execute = TRUE;
          break;

        case 'text':
          $query->fields([$name . '_value' => $randomizer->paragraphs(10)]);
          $execute = TRUE;
          break;

        case 'text_long':
          $query->fields([$name . '_value' => $randomizer->paragraphs(10)]);
          $execute = TRUE;
          break;

        case 'text_with_summary':
          $query->fields([$name . '_value' => $randomizer->paragraphs(10)]);
          $query->fields([$name . '_summary' => $randomizer->name(255)]);
          $execute = TRUE;
          break;
      }
      if ($execute) {
        $query->execute();
        $this->logger()->success(dt('!table table sanitized.', ['!table' => $table]));
      }
    }
  }

  /**
   * @hook on-event sql-sanitize-confirms
   *
   * @inheritdoc
   */
  public function messages(&$messages, InputInterface $input) {
    $messages[] = dt('Sanitize text Fields associated with the user.');
  }
}