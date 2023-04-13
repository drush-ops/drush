<?php

declare(strict_types=1);

namespace Drush\SiteAlias;

use Consolidation\SiteAlias\SiteAliasFileDiscovery;
use Drush\SiteAlias\Util\InternalYamlDataFileLoader;

/**
 * Discover alias files:
 *
 * - sitename.site.yml: contains multiple aliases, one for each of the
 *     environments of 'sitename'.
 */
class SiteAliasFileLoader extends \Consolidation\SiteAlias\SiteAliasFileLoader
{
    public function __construct(?SiteAliasFileDiscovery $discovery = null)
    {
        parent::__construct($discovery);
        $this->addLoader('yml', new InternalYamlDataFileLoader());
    }
}
