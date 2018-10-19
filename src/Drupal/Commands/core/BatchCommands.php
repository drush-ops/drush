<?php
namespace Drush\Drupal\Commands\core;

use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;

class BatchCommands extends DrushCommands
{
    /**
     * Process operations in the specified batch set.
     *
     * @command batch:process
     * @aliases batch-process
     * @param $batch_id The batch id that will be processed.
     * @hidden
     * @return \Consolidation\OutputFormatters\StructuredData\UnstructuredListData
     */
    public function process($batch_id, $options = ['format' => 'null'])
    {
        return new UnstructuredListData(drush_batch_command($batch_id));
    }
}
