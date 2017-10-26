<?php
namespace Drush\SiteAlias;

/**
 * Inflection interface for the site alias manager.
 */
interface SiteAliasManagerAwareInterface
{
    /**
     * @param SiteAliasManager $siteAliasManager
     */
    public function setSiteAliasManager($siteAliasManager);

    /**
     * @return SiteAliasManager
     */
    public function siteAliasManager();

    /**
     * @return bool
     */
    public function hasSiteAliasManager();
}
