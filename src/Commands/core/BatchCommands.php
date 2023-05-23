<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Boot\DrupalBootLevels;

class BatchCommands extends DrushCommands
{
    const PROCESS = 'batch:process';

    /**
     * Process operations in the specified batch set.
     */
    #[CLI\Command(name: self::PROCESS, aliases: ['batch-process'])]
    #[CLI\Argument(name: 'batch_id', description: 'The batch id that will be processed.')]
    #[CLI\Help(hidden: true)]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function process($batch_id, $options = ['format' => 'json']): UnstructuredListData
    {
        $return = drush_batch_command($batch_id);
        return new UnstructuredListData($return);
    }
}
