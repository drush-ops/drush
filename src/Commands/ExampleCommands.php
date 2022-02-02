<?php

namespace Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\AnnotatedCommand\CommandData;

class ExampleCommands extends DrushCommands
{
    /**
     * Demonstrate output formatters.  Default format is 'table'.
     *
     * @command example:table
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
     */
    public function exampleTable($options = ['format' => 'table']): RowsOfFields
    {
        $tableData = [
            'en' => [ 'first' => 'One',  'second' => 'Two',  'third' => 'Three' ],
            'de' => [ 'first' => 'Eins', 'second' => 'Zwei', 'third' => 'Drei'  ],
            'jp' => [ 'first' => 'Ichi', 'second' => 'Ni',   'third' => 'San'   ],
            'es' => [ 'first' => 'Uno',  'second' => 'Dos',  'third' => 'Tres'  ],
        ];
        $data = new RowsOfFields($tableData);

        // Add a render function to transform cell data when the output
        // format is a table, or similar.  This allows us to add color
        // information to the output without modifying the data cells when
        // using yaml or json output formats.
        $data->addRendererFunction(
            // n.b. There is a fourth parameter $rowData that may be added here.
            function ($key, $cellData, FormatterOptions $options, $rowData) {
                if ($key == 'first') {
                    return "<comment>$cellData</>";
                }
                return $cellData;
            }
        );

        return $data;
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
