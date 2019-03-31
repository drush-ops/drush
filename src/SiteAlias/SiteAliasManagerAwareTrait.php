<?php
namespace Drush\SiteAlias;

/**
 * Inflection trait for the site alias manager.
 *
 * @deprecated Use \Consolidation\SiteAlias\SiteAliasManagerAwareTrait
 */
trait SiteAliasManagerAwareTrait
{
    protected $siteAliasManager;

    /**
     * @inheritdoc
     */
    public function setSiteAliasManager($siteAliasManager)
    {
        $this->siteAliasManager = $siteAliasManager;
    }

    /**
     * @return SiteAliasManager
     */
    public function siteAliasManager()
    {
        return $this->siteAliasManager;
    }

    /**
     * @inheritdoc
     */
    public function hasSiteAliasManager()
    {
        return isset($this->siteAliasManager);
    }
}
