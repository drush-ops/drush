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
    protected $root = '';

    /**
     * Constructor for SiteAliasManager
     *
     * @param SiteAliasFileLoader|null $aliasLoader an alias loader
     */
    public function __construct($aliasLoader = null, $root = '')
    {
        $this->aliasLoader = $aliasLoader ?: new SiteAliasFileLoader();
        $this->legacyAliasConverter = new LegacyAliasConverter($this->aliasLoader->discovery());
        $this->specParser = new SiteSpecParser();
        $this->selfAliasRecord = new AliasRecord();
        $this->root = $root;
    }

    /**
     * Allow configuration data to be used in replacements in the alias file.
     */
    public function setReferenceData($data)
    {
        $this->aliasLoader->setReferenceData($data);
    }

    /**
     * Inject the root of the selected site
     *
     * @param string $root
     * @return $this
     */
    public function setRoot($root)
    {
        $this->root = $root;
        return $this;
    }

    /**
     * Add a search location to our site alias discovery object.
     *
     * @param string $path
     *
     * @return $this
     */
    public function addSearchLocation($path)
    {
        $this->aliasLoader->discovery()->addSearchLocation($path);
        return $this;
    }

    /**
     * Add search locations to our site alias discovery object.
     *
     * @param array $paths Any path provided in --alias-path option
     *   or drush.path.alias-path configuration item.
     *
     * @return $this
     */
    public function addSearchLocations(array $paths)
    {
        foreach ($paths as $path) {
            $this->aliasLoader->discovery()->addSearchLocation($path);
        }
        return $this;
    }

    /**
     * Return all of the paths where alias files may be found.
     * @return string[]
     */
    public function searchLocations()
    {
        return $this->aliasLoader->discovery()->searchLocations();
    }

    /**
     * Get an alias record by name, or convert a site specification
     * into an alias record via the site alias spec parser. If a
     * simple alias name is provided (e.g. '@alias'), it is interpreted
     * as a sitename, and the default environment for that site is returned.
     *
     * @param string $name Alias name or site specification
     *
     * @return AliasRecord|false
     */
    public function get($name)
    {
        if (SiteAliasName::isAliasName($name)) {
            return $this->getAlias($name);
        }

        if ($this->specParser->validSiteSpec($name)) {
            return new AliasRecord($this->specParser->parse($name, $this->root), $name);
        }

        return false;
    }

    /**
     * Get the '@self' alias record.
     *
     * @return AliasRecord
     */
    public function getSelf()
    {
        return $this->selfAliasRecord;
    }

    /**
     * Force-set the current @self alias.
     *
     * @param AliasRecord $selfAliasRecord
     * @return $this
     */
    public function setSelf(AliasRecord $selfAliasRecord)
    {
        $this->selfAliasRecord = $selfAliasRecord;
        $this->setRoot($selfAliasRecord->localRoot());
        return $this;
    }

    /**
     * Get an alias record from a name. Does not accept site specifications.
     *
     * @param string $aliasName alias name
     *
     * @return AliasRecord
     */
    public function getAlias($aliasName)
    {
        $aliasName = SiteAliasName::parse($aliasName);

        if ($aliasName->isSelf()) {
            return $this->getSelf();
        }

        if ($aliasName->isNone()) {
            return new AliasRecord([], '@none');
        }

        // Search through all search locations, load
        // matching and potentially-matching alias files,
        // and return the alias matching the provided name.
        return $this->aliasLoader->load($aliasName);
    }

    /**
     * Given a simple alias name, e.g. '@alias', returns all of the
     * environments in the specified site.
     *
     * If the provided name is a site specification et. al.,
     * then this method will return 'false'.
     *
     * @param string $name Alias name or site specification
     * @return AliasRecord[]|false
     */
    public function getMultiple($name)
    {
        if (empty($name)) {
            return $this->aliasLoader->loadAll();
        }

        if (!SiteAliasName::isAliasName($name)) {
            return false;
        }

        // Trim off the '@' and load all that match
        $result = $this->aliasLoader->loadMultiple(ltrim($name, '@'));

        // Special checking for @self
        if ($name == '@self') {
            $self = $this->getSelf();
            $result = array_merge(
                ['@self' => $self],
                $result
            );
        }

        return $result;
    }

    /**
     * Return the paths to all alias files in all search locations known
     * to the alias manager.
     *
     * @return string[]
     */
    public function listAllFilePaths()
    {
        return $this->aliasLoader->listAll();
    }
}
