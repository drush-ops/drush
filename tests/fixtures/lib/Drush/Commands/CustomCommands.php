<?php

namespace Custom\Library\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;

class CustomCommands extends DrushCommands
{
    /**
     * Auto-discoverable custom command. Used for Drush testing.
     */
    #[CLI\Command(name: 'custom_cmd')]
    #[CLI\Help(hidden: true)]
    public function customCommand(): void
    {
        $this->io()->note('Hello world!');
    }
}
