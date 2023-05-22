<?php

declare(strict_types=1);

namespace Drush\Commands\drush_extensions;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

class DrushExtensionsCommands extends DrushCommands
{
    /**
     * Command to load from this file using drush config.
     *
     * @command drush-extensions-hello
     * @bootstrap none
     * @hidden
     */
    public function customCommand(SymfonyStyle $io): void
    {
        $io->text('Hello world!');
    }
}
