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

class ExampleCommandFile
{
    /**
     * Demonstrate Robo formatters.  Default format is 'table'.
     *
     * @field-labels
     *   first: I
     *   second: II
     *   third: III
     * @default-string-field second
     * @usage example:formatters --format=yaml
     * @usage example:formatters --format=csv
     * @usage example:formatters --fields=first,third
     * @usage example:formatters --fields=III,II
     * @aliases tf
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
     * @hook alter example:table
     * @option french Add a row with French numbers.
     * @usage example:formatters --french
     */
    public function alterFormatters($result, CommandData $commandData)
    {
        if ($commandData->input()->getOption('french')) {
            $result['fr'] = [ 'first' => 'Un',  'second' => 'Deux',  'third' => 'Trois'  ];
        }

        return $result;
    }
}
