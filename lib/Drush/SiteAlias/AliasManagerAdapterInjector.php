<?php
namespace Drush\SiteAlias;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Drush;

class AliasManagerAdapterInjector
{
    protected static $aliasManager = null;

    public function inflect($command)
    {
        if ($command instanceof SiteAliasManagerAwareInterface) {
            $command->setSiteAliasManager(Drush::aliasManager());
        }
    }
}
