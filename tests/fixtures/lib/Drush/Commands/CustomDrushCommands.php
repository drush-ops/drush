<?php

namespace Custom\Library\Drush\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

class CustomDrushCommands extends DrushCommands
{
    /**
     * Auto-discoverable custom command. Used for Drush testing.
     *
     * @command custom_cmd
     * @hidden
     */
    public function customCommand(SymfonyStyle $io): void
    {
        $io->text('Hello world!');
    }
}
