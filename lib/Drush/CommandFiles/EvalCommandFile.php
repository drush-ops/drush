<?php
namespace Drush\CommandFiles;

/**
 * @file
 *   Evaluate PHP code.
 */

class EvalCommandFile
{
  /**
   * Evaluate arbitrary php code after bootstrapping Drupal (if available).
   *
   * @param string $php Code to execute.
   * @aliases php-eval, eval, ev
   * @usage php-eval 'variable_set("hello", "world");'
   *   Sets the hello variable using Drupal API.
   * @usage php-eval '$node = node_load(1); return $node->title;'
   *   Loads node with nid 1 and then prints its title.
   * @usage php-eval "file_unmanaged_copy('$HOME/Pictures/image.jpg', 'public://image.jpg');"
   *   Copies a file whose path is determined by an environment's variable.
   *   Note the use of double quotes so the variable $HOME gets replaced by
   *   its value.
   * @usage php-eval "node_access_rebuild();"
   *   Rebuild node access permissions.
   * @default-format var_export
   */
  public function phpEval($php, $options =
    [
      'format' => 'var_export',
    ]) {
    return eval($php . ';');
  }
}
