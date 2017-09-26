<?php
namespace Drush\Commands\core;

/**
 * @file
 *   Evaluate PHP code.
 */

class EvalCommands
{
    /**
     * Evaluate arbitrary php code after bootstrapping Drupal (if available).
     *
     * @command php:eval
     * @param string $php Code to execute.
     * @aliases eval,ev,php-eval
     * @usage php-eval '$node = node_load(1); return $node->label();'
     *   Loads node with nid 1 and then prints its title.
     * @usage php-eval "file_unmanaged_copy('$HOME/Pictures/image.jpg', 'public://image.jpg');"
     *   Copies a file whose path is determined by an environment's variable.
     *   Note the use of double quotes so the variable $HOME gets replaced by
     *   its value.
     * @usage php-eval "node_access_rebuild();"
     *   Rebuild node access permissions.
     * @bootstrap max
     */
    public function phpEval($php, $options = ['format' => 'var_export'])
    {
        return eval($php . ';');
    }
}
