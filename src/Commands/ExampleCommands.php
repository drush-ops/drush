<?php

declare(strict_types=1);

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Attributes as CLI;

class ExampleCommands extends DrushCommands
{
    const TABLE = 'example:table';

    /**
     * Demonstrate output formatters.  Default format is 'table'.
     *
     * @todo @default-string-field second
     */
    #[CLI\Command(name: self::TABLE, aliases: ['tf'])]
    #[CLI\Help(hidden: true)]
    #[CLI\FieldLabels(labels: ['first' => 'I', 'second' => 'II', 'third' => 'III'])]
    #[CLI\Usage(name: 'drush example:table --format=yaml', description: '')]
    #[CLI\Usage(name: 'drush example:table --format=csv', description: '')]
    #[CLI\Usage(name: 'drush example:table --fiends=first,third', description: '')]
    #[CLI\Usage(name: 'drush example:table --fields=III,II', description: '')]
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
     * Demonstrate an alter hook with an option.
     */
    #[CLI\Usage(name: 'drush example-table --french', description: 'Table with a French row.')]
    #[CLI\Option(name: 'french', description: 'Add a row with French numbers.')]
    #[CLI\Hook(type: HookManager::ALTER_RESULT, target: self::TABLE)]
    public function alterFormatters($result, CommandData $commandData)
    {
        if ($commandData->input()->getOption('french')) {
            $result['fr'] = [ 'first' => 'Un',  'second' => 'Deux',  'third' => 'Trois'  ];
        }

        return $result;
    }
}
