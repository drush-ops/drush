<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Commands\DrushCommands;

class BatchCommands extends DrushCommands
{
    /**
     * Process operations in the specified batch set.
     *
     * @command batch:process
     * @aliases batch-process
     * @param $batch_id The batch id that will be processed.
     * @hidden
     */
    public function process($batch_id, $options = ['format' => 'json']): UnstructuredListData
    {
        $return = drush_batch_command($batch_id);
        return new UnstructuredListData($return);
    }
}
