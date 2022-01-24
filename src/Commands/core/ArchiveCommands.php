<?php

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class ArchiveCommands extends DrushCommands
{
    /**
     * Backup your code, files, and database into a single file.
     *
     * @command archive:dump
     * @aliases ard
     */
    public function dump(): int
    {
        throw new \Exception('Test archive:dump command');
    }
}
