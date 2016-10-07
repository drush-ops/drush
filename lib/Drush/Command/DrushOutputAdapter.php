<?php

/**
 * @file
 * Definition of Drush\Command\DrushOutputAdapter.
 */

namespace Drush\Command;

use Symfony\Component\Console\Output\Output;

/**
 * Adapter for Symfony Console OutputInterface
 *
 * This class can serve as a stand-in wherever an OutputInterface
 * is needed.  It calls through to drush_print().
 * This object should not be used directly; it exists only in
 * the Drush 8.x branch.
 */
class DrushOutputAdapter extends Output {
    protected function doWrite($message, $newline)
    {
        drush_print($message, 0, null, $newline);
    }
}
