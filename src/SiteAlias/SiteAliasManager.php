<?php
namespace Drush\SiteAlias;

use Drush\SiteAlias\SiteSpecParser;

/**
 * Site Alias manager
 */
class SiteAliasManager
{
    protected $aliasLoader;
    protected $selfAliasRecord;
    protected $specParser;

    public function __construct($aliasLoader = null)
    {
        $this->aliasLoader = $aliasLoader ?: new SiteAliasFileLoader();
        $this->specParser = new SiteSpecParser();
        $this->selfAliasRecord = new AliasRecord();
    }

    public function addSearchLocation($path)
    {
        $this->aliasLoader->discovery()->addSearchLocation($path);
        return $this;
    }

    public function get($name)
    {
        if (SiteAliasName::isAliasName($name)) {
            return $this->getAlias($name);
        }

        return $this->specParser->validSiteSpec($arg);


        return false;
    }

    /**
     * @return AliasRecord
     */
    public function getSelf()
    {
        return $this->selfAliasRecord;
    }

    /**
     * Return the current @self alias.
     *
     * @param AliasRecord $selfAliasRecord
     * @return $this
     */
    public function setSelf(AliasRecord $selfAliasRecord)
    {
        $this->selfAliasRecord = $selfAliasRecord;
        return $this;
    }

    public function findSelf($aliasName, $root, $uri)
    {
        $selfAliasRecord = $this->buildSelf($aliasName, $root, $uri);
        if (!$selfAliasRecord) {
            throw new \Exception("The alias $aliasName could not be found.");
        }
        $this->setSelf($selfAliasRecord);
        return $this->getSelf();
    }

    public function getAlias($aliasName)
    {
        $aliasName = new SiteAliasName($aliasName);

        if ($aliasName->isSelf()) {
            return $this->getSelf();
        }

        if ($aliasName->isNone()) {
            return new AliasRecord();
        }

        // Search through all search locations, load
        // matching and potentially-matching alias files,
        // and return the alias matching the provided name.
        return $this->aliasLoader->load($aliasName);
    }

    protected function buildSelf($aliasName, $root, $uri)
    {
        if (SiteAliasName::isAliasName($aliasName)) {
            return $this->getAlias($aliasName);
        }

        $specParser = new SiteSpecParser();
        if ($specParser->validSiteSpec($aliasName)) {
            return new AliasRecord($specParser->parse($aliasName, $root));
        }

        if (empty($uri)) {
            $uri = 'default';
        }

        return new AliasRecord(
            [
                'root' => $root,
                'uri' => $uri,
            ]
        );
    }
}
