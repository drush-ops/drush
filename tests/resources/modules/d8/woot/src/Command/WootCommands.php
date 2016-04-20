<?php
namespace Drupal\woot\Command;

/**
 * For commands that are parts of modules, Drush expects to find commandfiles in
 * __MODULE__/src/Command, and the namespace is Drupal/__MODULE__/Command.
 */
class WootCommands
{
    /**
     * Woot mightily.
     *
     * @aliases wt
     */
    public function woot()
    {
      return 'Woot!';
    }

    /**
     * This is the my-cat command
     *
     * This command will concatinate two parameters. If the --flip flag
     * is provided, then the result is the concatination of two and one.
     *
     * @param string $one The first parameter.
     * @param string $two The other parameter.
     * @option boolean $flip Whether or not the second parameter should come first in the result.
     * @aliases c
     * @usage bet alpha --flip
     *   Concatinate "alpha" and "bet".
     */
    public function myCat($one, $two = '', $options = ['flip' => false])
    {
        if ($options['flip']) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }
}
