<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

class BatchCommands extends DrushCommands
{
    const PROCESS = 'batch:process';

    /**
     * Process operations in the specified batch set.
     */
    #[CLI\Command(name: self::PROCESS, aliases: ['batch-process'])]
    #[CLI\Argument(name: 'batch_id', description: 'The batch id that will be processed.')]
    #[CLI\Help(hidden: true)]
    public function process($batch_id, $options = ['format' => 'json']): UnstructuredListData
    {
        $return = drush_batch_command($batch_id);
        return new UnstructuredListData($return);
    }
}
