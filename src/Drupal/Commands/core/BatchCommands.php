<?php
namespace Drush\Drupal\Commands\core;

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
    public function process($batch_id)
    {
        return drush_batch_command($batch_id);
    }
}
