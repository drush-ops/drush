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

    public function setRoot($root)
    {
        $this->root = $root;
    }

    /**
     * Add a search location to our site alias discovery object.
     *
     * @param string $path
     * @return $this
     */
    public function addSearchLocation($path)
    {
        $this->aliasLoader->discovery()->addSearchLocation($path);
        return $this;
    }

    /**
     * Get an alias record by name, or convert a site specification
     * into an alias record via the site alias spec parser. If a
     * simple alias name is provided (e.g. '@alias'), it is interpreted
     * as a sitename, and the default environment for that site is returned.
     *
     * @param string $name Alias name or site specification
     *
     * @return AliasRecord
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
     * During bootstrap, finds the currently selected site from the parameters
     * provided on the commandline.
     *
     * @param string $aliasName An alias name or site specification
     * @param string $root The default Drupal root (from --root or cwd)
     * @param string $uri The selected multisite
     * @param string $cwd The cwd at the time Drush was first called
     * @return type
     */
    public function findSelf($aliasName, $root, $uri)
    {
        $selfAliasRecord = $this->buildSelf($aliasName, $root, $uri);
        if (!$selfAliasRecord) {
            throw new \Exception("The alias $aliasName could not be found.");
        }
        $this->setSelf($selfAliasRecord);
        return $this->getSelf();
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
        $aliasName = new SiteAliasName($aliasName);

        if ($aliasName->isSelf()) {
            return $this->getSelf();
        }

        if ($aliasName->isNone()) {
            return new AliasRecord([], '@none');
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

    /**
     * Given a simple alias name, e.g. '@alias', returns either all of
     * the sites and environments in that alias group, or all of the
     * environments in the specified site.
     *
     * If the provided name is a site specification, or if it contains
     * a group or environment ('@group.site' or '@site.env' or '@group.site.env'),
     * then this method will return 'false'.
     *
     * @param string $name Alias name or site specification
     * @return AliasRecord[]|false
     */
    public function getMultiple($name)
    {
        $this->legacyAliasConverter->convertOnce();

        if (empty($name)) {
            return $this->aliasLoader->loadAll();
        }

        if (!SiteAliasName::isAliasName($name)) {
            return false;
        }

        $aliasName = new SiteAliasName($name);
        return $this->aliasLoader->loadMultiple($aliasName);
    }

    /**
     * Either look up the specified alias name / site spec,
     * or, if those are invalid, then generate one from
     * the provided root and URI.
     */
    protected function buildSelf($aliasName, $root, $uri)
    {
        // If the user specified an @alias, that takes precidence.
        if (SiteAliasName::isAliasName($aliasName)) {
            return $this->getAlias($aliasName);
        }

        // Ditto for a site spec (/path/to/drupal#uri)
        $specParser = new SiteSpecParser();
        if ($specParser->validSiteSpec($aliasName)) {
            return new AliasRecord($specParser->parse($aliasName, $root), $aliasName);
        }

        // If there is no root, then return '@none'
        if (!$root) {
            return new AliasRecord([], '@none');
        }

        // If there is no URI specified, we will allow it to
        // remain empty for now. We will refine it later via
        // Application::refineUriSelection(), which is called
        // in Preflight::doRun(). This method will set it to
        // 'default' if no better directory can be devined.

        // Create the 'self' alias record. Note that the self
        // record will be named '@self' if it is manually constructed
        // here, and will otherwise have the name of the
        // alias or site specfication used by the user.
        return new AliasRecord(
            [
                'root' => $root,
                'uri' => $uri,
            ],
            '@self'
        );
    }
}
