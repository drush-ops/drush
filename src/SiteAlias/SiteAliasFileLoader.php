<?php
namespace Drush\SiteAlias;

use Drush\SiteAlias\Util\InternalYamlDataFileLoader;

/**
 * Discover alias files:
 *
 * - sitename.site.yml: contains multiple aliases, one for each of the
 *     environments of 'sitename'.
 */
class SiteAliasFileLoader extends \Consolidation\SiteAlias\SiteAliasFileLoader
{
    /**
     * SiteAliasFileLoader constructor
     *
     * @param SiteAliasFileDiscovery|null $discovery
     */
    public function __construct($discovery = null)
    {
        parent::__construct($discovery);
        $this->addLoader('yml', new InternalYamlDataFileLoader());
    }
}
