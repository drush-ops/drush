<?php

declare(strict_types=1);

namespace Drush\Commands\drush_extensions;

use Drush\Commands\DrushCommands;

class DrushExtensionsCommands extends DrushCommands
{
    /**
     * Command to load from this file using drush config.
     *
     * @command drush-extensions-hello
     * @bootstrap none
     * @hidden
     */
    public function customCommand(): void
    {
        $this->io()->text('Hello world!');
    }
}
