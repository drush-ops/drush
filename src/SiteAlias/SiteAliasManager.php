<?php
namespace Drush\SiteAlias;

use Drush\SiteAlias\SiteSpecParser;

/**
 * Site Alias manager
 */
class SiteAliasManager
{
    protected $discovery;
    protected $selfAliasRecord;

    public function __consrtuct($discovery = null)
    {
        $this->discovery = $discovery ?: new SiteAliasFileDiscovery();
        $this->selfAliasRecord = new AliasRecord();
    }

    public function addSearchLocation($path)
    {
        $this->discovery->addSearchLocation($path);
        return $this;
    }

    public function findSelf($aliasName, $root, $uri)
    {
        $selfAliasRecord = $this->buildSelf($aliasName, $root, $uri);
        $this->setSelf($selfAliasRecord);
        return $this->getSelf();
    }

    public function setSelf($selfAliasRecord)
    {
        $this->selfAliasRecord = $selfAliasRecord;
        return $this;
    }

    public function getSelf()
    {
        return $this->selfAliasRecord;
    }

    protected function buildSelf($aliasName, $root, $uri)
    {
        $specParser = new SiteSpecParser();

        if (SiteAliasName::isAliasName($aliasName)) {
            return $this->getAlias($aliasName);
        }

        if ($specParser->validSiteSpec($aliasName)) {
            return new AliasRecord($specParser->parse($aliasName, $root));
        }

        if (empty($uri)) {
            $uri = 'default';
        }

        return new AliasRecord([
            'root' => $root,
            'uri' => $uri,
        ]);
    }

    public function getAlias($aliasName)
    {
        $aliasName = new SiteAliasName($aliasName);

        if ($aliasName.isSelf()) {
            return $this->getSelf();
        }

        if ($aliasName.isNone()) {
            return new AliasRecord();
        }

        // TODO: Search through all search locations, load
        // matching and potentially-matching alias files,
        // and return the alias matching the provided name.
        return new AliasRecord();
    }
}
