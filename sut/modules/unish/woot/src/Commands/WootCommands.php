<?php

declare(strict_types=1);

namespace Drupal\woot\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class WootCommands
{
    public function __construct(protected string $appRoot) {}

    /**
     * Woot mightily.
     *
     * @command woot
     * @aliases wt
     */
    public function woot(): string
    {
        return 'Woot!';
    }

    /**
     * @command woot:root
     */
    public function appRoot(): string
    {
        return "The app root is {$this->appRoot}";
    }

    /**
     * This is the my-cat command
     *
     * This command will concatenate two parameters. If the --flip flag
     * is provided, then the result is the concatination of two and one.
     *
     * @command my-cat
     * @param string $one The first parameter.
     * @param string $two The other parameter.
     * @option boolean $flip Whether or not the second parameter should come first in the result.
     * @aliases c
     * @usage bet alpha --flip
     *   Concatinate "alpha" and "bet".
     */
    public function myCat($one, $two = '', $options = ['flip' => false]): string
    {
        if ($options['flip']) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }

    /**
     * Demonstrate formatters.  Default format is 'table'.
     *
     * @command try:formatters
     * @field-labels
     *   first: I
     *   second: II
     *   third: III
     * @usage try:formatters --format=yaml
     *   Emit yaml.
     * @usage try:formatters --format=csv
     *   Emit CSV.
     * @usage try:formatters --fields=first,third
     *   Emit some fields.
     * @usage try:formatters --fields=III,II
     *   Emit other fields.
     * @aliases try-formatters
     */
    public function tryFormatters($options = ['format' => 'table', 'fields' => '']): RowsOfFields
    {
        $outputData = [
            'en' => [ 'first' => 'One',  'second' => 'Two',  'third' => 'Three' ],
            'de' => [ 'first' => 'Eins', 'second' => 'Zwei', 'third' => 'Drei'  ],
            'jp' => [ 'first' => 'Ichi', 'second' => 'Ni',   'third' => 'San'   ],
            'es' => [ 'first' => 'Uno',  'second' => 'Dos',  'third' => 'Tres'  ],
        ];
        return new RowsOfFields($outputData);
    }

    /**
     * This command info is altered.
     *
     * @command woot:altered
     * @aliases woot-initial-alias
     */
    public function wootAltered()
    {
    }
}
