<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;

/*
 * Common validation providers. Use them by adding an annotation to your method.
 */
class ValidatorsCommands {

  /**
   * Validate that passed entity names are valid.
   * @see \Drush\Commands\core\ViewsCommands::execute for an example.
   *
   * @hook validate @validate-entity-load
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateEntityLoad(CommandData $commandData) {
    list($entity_type, $arg_name) = explode(' ', $commandData->annotationData()->get('validate-entity-load', NULL));
    $names = _convert_csv_to_array($commandData->input()->getArgument($arg_name));
    $loaded = \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($names);
    if ($missing = array_diff($names, array_keys($loaded))) {
      $msg = dt('Unable to load the !type: !str', ['!type' => $entity_type, '!str' => implode(', ', $missing)]);
      return new CommandError($msg);
    }
  }

  /**
   * Validate that the file path exists.
   *
   * Annotation value should be the name of the argument containing the path.
   *
   * @hook validate @validate-file-exists
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateFileExists(CommandData $commandData) {
    $missing = [];
    $arg_names = _convert_csv_to_array($commandData->annotationData()->get('validate-file-exists', NULL));
    foreach ($arg_names as $arg_name) {
      $path = $commandData->input()->getArgument($arg_name);
      if (!empty($path) && !file_exists($path)) {
        $missing[] = $path;
      }
    }

    if ($missing) {
      $msg = dt('File(s) not found: !paths', ['!paths' => implode(', ', $missing)]);
      return new CommandError($msg);
    }
  }

  /**
   * Validate that required PHP extension exists.
   *
   * Annotation value should be extension name. If multiple, delimit by a comma.
   *
   * @hook validate @validate-php-extension
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  function validate(CommandData $commandData) {
    $missing = [];
    $arg_names = _convert_csv_to_array($commandData->annotationData()->get('validate-php-extension', NULL));
    foreach ($arg_names as $arg_name) {
      if (!extension_loaded($arg_name)) {
        $missing[] = $arg_name;
      }
    }

    if ($missing) {
      $args = array('!command' => $commandData->input(), '!dependencies' => implode(', ', $missing));
      return new CommandError(dt('Command !command needs the following PHP extensions installed and enabled: !dependencies.', $args));
    }
  }

}
