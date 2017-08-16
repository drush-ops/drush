<?php
namespace Drush\SiteAlias;

trait SiteAliasManagerAwareTrait
{
    protected $siteAliasManager;

    public function setSiteAliasManager($siteAliasManager)
    {
        $this->siteAliasManager = $siteAliasManager;
    }

    public function siteAliasManager()
    {
        return $this->siteAliasManager;
    }

    public function hasSiteAliasManager()
    {
        return isset($this->siteAliasManager);
    }
}
