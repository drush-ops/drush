<?php
namespace Drush\SiteAlias;

/**
 * Site Alias manager
 */
class SiteAliasManager
{
    protected $aliasLoader;
    protected $legacyAliasConverter;
    protected $selfAliasRecord;
    protected $specParser;

    public function __construct($aliasLoader = null)
    {
        $this->aliasLoader = $aliasLoader ?: new SiteAliasFileLoader();
        $this->legacyAliasConverter = new LegacyAliasConverter($this->aliasLoader->discovery());
        $this->specParser = new SiteSpecParser();
        $this->selfAliasRecord = new AliasRecord();
    }

    public function addSearchLocation($path)
    {
        $this->aliasLoader->discovery()->addSearchLocation($path);
        return $this;
    }

    /**
     * @return AliasRecord
     */
    public function get($name)
    {
        if (SiteAliasName::isAliasName($name)) {
            return $this->getAlias($name);
        }

        if ($this->specParser->validSiteSpec($name)) {
            return new AliasRecord($this->specParser->parse($name));
        }

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
            return new AliasRecord([], $aliasName);
        }

        // Check to see if there are any legacy alias files that
        // need to be converted.
        // TODO: provide an enable / disable switch for this?
        $this->legacyAliasConverter->convertOnce();

        // Search through all search locations, load
        // matching and potentially-matching alias files,
        // and return the alias matching the provided name.
        return $this->aliasLoader->load($aliasName);
    }

    public function loadAll()
    {
        $this->legacyAliasConverter->convertOnce();

        return $this->aliasLoader->loadAll();
    }

    protected function buildSelf($aliasName, $root, $uri)
    {
        if (SiteAliasName::isAliasName($aliasName)) {
            return $this->getAlias($aliasName);
        }

        $specParser = new SiteSpecParser();
        if ($specParser->validSiteSpec($aliasName)) {
            return new AliasRecord($specParser->parse($aliasName, $root), $aliasName);
        }

        if (empty($uri)) {
            $uri = 'default';
        }

        return new AliasRecord(
            [
                'root' => $root,
                'uri' => $uri,
            ],
            '@self'
        );
    }
}
