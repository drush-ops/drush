<?php

namespace Custom\Library\Drush\Commands;

use Drush\Commands\DrushCommands;

class CustomDrushCommands extends DrushCommands
{
    /**
     * A custom command provided by a custom non-Drupal library
     *
     * @command custom_cmd
     */
    public function customCommand(): void
    {
        $this->io()->text('Hello world!');
    }
}
