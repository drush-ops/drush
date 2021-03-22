<?php

namespace Custom\Library\Drush\Commands;

use Drush\Commands\DrushCommands;

class CustomDrushCommands extends DrushCommands
{
    /**
     * Auto-discoverable custom command
     *
     * @command custom_cmd
     */
    public function customCommand(): void
    {
        $this->io()->text('Hello world!');
    }
}
