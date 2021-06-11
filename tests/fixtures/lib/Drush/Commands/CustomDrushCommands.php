<?php

namespace Custom\Library\Drush\Commands;

use Drush\Commands\DrushCommands;

class CustomDrushCommands extends DrushCommands
{
    /**
     * Auto-discoverable custom command. Used for Drush testing.
     *
     * @command custom_cmd
     * @hidden
     */
    public function customCommand(): void
    {
        $this->io()->text('Hello world!');
    }
}
