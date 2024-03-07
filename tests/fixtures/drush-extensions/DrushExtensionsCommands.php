<?php

declare(strict_types=1);

namespace Drush\Commands\drush_extensions;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
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
        $this->io()->note('Hello world!');
    }
}
