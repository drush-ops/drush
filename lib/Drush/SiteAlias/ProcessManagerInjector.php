<?php

namespace Drush\SiteAlias;

use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Drush\SiteAlias\ProcessManager;
use Drush\Drush;

class ProcessManagerInjector
{
    protected static $processManager = null;

    public function inflect($command)
    {
        if ($command instanceof ProcessManagerAwareInterface) {
            $command->setProcessManager(Drush::processManager());
        }
    }
}
