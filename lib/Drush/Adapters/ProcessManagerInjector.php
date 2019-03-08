<?php

namespace Drush\Adapters;

use Consolidation\SiteProcess\ProcessManager;

class ProcessManagerInjector
{
    protected static $processManager = null;

    public function inflect($command)
    {
        if ($command instanceof SiteAliasManagerAwareInterface) {
            $command->setSiteAliasManager(static::getProcessManagerAdapter());
        }
    }

    protected static function getProcessManagerAdapter()
    {
        if (!static::$processManager) {
            static::$processManager = ProcessManager::createDefault();
            static::$processManager->setConfig(new DrushConfig());
            // TODO: static::$processManager->setConfigRuntime()
        }

        return static::$processManager;
    }
}
