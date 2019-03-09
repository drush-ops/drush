<?php
namespace Drush\SiteAlias;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteAlias\SiteAlias;

class AliasManagerAdapter implements SiteAliasManagerInterface
{
    /**
     * @inheritdoc
     */
    public function searchLocations()
    {
        // not supported
        return [];
    }

    /**
     * @inheritdoc
     */
    public function get($name)
    {
        return $this->getAlias($name);
    }

    /**
     * @inheritdoc
     */
    public function getSelf()
    {
        return $this->getAlias('@self');
    }

    /**
     * @inheritdoc
     */
    public function getAlias($aliasName)
    {
        $record = drush_sitealias_get_record($aliasName);

        // TODO: Convert $record to new site alias layout

        return new SiteAlias($record, $aliasName);
    }

    /**
     * @inheritdoc
     */
    public function getMultiple($name = '')
    {
        // Not supported
        return false;
    }

    /**
     * @inheritdoc
     */
    public function listAllFilePaths($location = '')
    {
        // Not supported
        return [];
    }
}
