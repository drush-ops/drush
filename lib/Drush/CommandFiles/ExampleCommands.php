<?php
namespace Drush\CommandFiles;

/**
 * @file
 *   Set up local Drush configuration.
 */

use Drush\Log\LogLevel;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

use Consolidation\AnnotatedCommand\CommandData;

class ExampleCommands extends DrushCommands
{
    /**
     * Demonstrate output formatters.  Default format is 'table'.
     *
     * @command example-table
     * @field-labels
     *   first: I
     *   second: II
     *   third: III
     * @default-string-field second
     * @usage example-table --format=yaml
     * @usage example-table --format=csv
     * @usage example-table --fields=first,third
     * @usage example-table --fields=III,II
     * @aliases tf
     * @hidden
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function exampleTable($options = ['format' => 'table', 'fields' => ''])
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
     * Demonstrate an alter hook with an option
     *
     * @hook alter example-table
     * @option french Add a row with French numbers.
     * @usage example-table --french
     */
    public function alterFormatters($result, CommandData $commandData)
    {
        if ($commandData->input()->getOption('french')) {
            $result['fr'] = [ 'first' => 'Un',  'second' => 'Deux',  'third' => 'Trois'  ];
        }

        return $result;
    }
}
