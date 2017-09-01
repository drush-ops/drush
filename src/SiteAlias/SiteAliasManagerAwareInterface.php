<?php
namespace Drush\SiteAlias;

interface SiteAliasManagerAwareInterface
{
    public function setSiteAliasManager($siteAliasManager);

    public function siteAliasManager();

    public function hasSiteAliasManager();
}
