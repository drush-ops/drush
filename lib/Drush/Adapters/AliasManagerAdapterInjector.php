<?php
namespace Drush\Adapters;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerInterface;

class AliasManagerAdapterInjector
{
    protected static $aliasManager = null;

    public function inflect($command)
    {
        if ($command instanceof SiteAliasManagerAwareInterface) {
            $command->setSiteAliasManager(static::getAliasManagerAdapter());
        }
    }

    protected static function getAliasManagerAdapter()
    {
        if (!static::$aliasManager) {
            static::$aliasManager = new AliasManagerAdapter();
        }

        return static::$aliasManager;
    }
}
