<?php
namespace Drupal\woot\Command;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

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

    /**
     * Demonstrate formatters.  Default format is 'table'.
     *
     * @field-labels
     *   first: I
     *   second: II
     *   third: III
     * @usage try:formatters --format=yaml
     * @usage try:formatters --format=csv
     * @usage try:formatters --fields=first,third
     * @usage try:formatters --fields=III,II
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function tryFormatters($options = ['format' => 'table', 'fields' => ''])
    {
        $outputData = [
            'en' => [ 'first' => 'One',  'second' => 'Two',  'third' => 'Three' ],
            'de' => [ 'first' => 'Eins', 'second' => 'Zwei', 'third' => 'Drei'  ],
            'jp' => [ 'first' => 'Ichi', 'second' => 'Ni',   'third' => 'San'   ],
            'es' => [ 'first' => 'Uno',  'second' => 'Dos',  'third' => 'Tres'  ],
        ];
        return new RowsOfFields($outputData);
    }
}
