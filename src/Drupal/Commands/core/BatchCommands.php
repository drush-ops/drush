<?php
namespace Drush\Drupal\Commands\core;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Output\OutputInterface;

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
        // Suppress the output of the batch process command. This is intended to
        // be passed to the initiating command rather than being output to the
        // console.
        $this->output()->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        return drush_batch_command($batch_id);
    }
}
