<?php
namespace Drush\SiteAlias;

use Drush\Config\Environment;
use Drush\Preflight\PreflightArgsInterface;

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
     * @param array $paths
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
     * @param PreflightArgsInterface $preflightArgs An alias name or site specification
     * @param \Drush\Config\Environment $environment
     * @param string $root The default Drupal root (from site:set, --root or cwd)
     *
     * @return \Drush\SiteAlias\type
     * @throws \Exception
     */
    public function findSelf(PreflightArgsInterface $preflightArgs, Environment $environment, $root)
    {
        $aliasName = $preflightArgs->alias();
        $selfAliasRecord = $this->buildSelf($preflightArgs, $environment, $root);
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
     * Return the paths to all alias files in all search locations known
     * to the alias manager.
     *
     * @return string[]
     */
    public function listAllFilePaths()
    {
        return $this->aliasLoader->listAll();
    }

    /**
     * Either look up the specified alias name / site spec,
     * or, if those are invalid, then generate one from
     * the provided root and URI.
     *
     * @param \Drush\Preflight\PreflightArgsInterface $preflightArgs
     * @param \Drush\Config\Environment $environment
     * @param $root
     *
     * @return \Drush\SiteAlias\AliasRecord
     */
    protected function buildSelf(PreflightArgsInterface $preflightArgs, Environment $environment, $root)
    {
        $aliasName = $preflightArgs->alias();

        // If the user specified an @alias, that takes precidence.
        if (SiteAliasName::isAliasName($aliasName)) {
            return $this->getAlias($aliasName);
        }

        // Ditto for a site spec (/path/to/drupal#uri)
        $specParser = new SiteSpecParser();
        if ($specParser->validSiteSpec($aliasName)) {
            return new AliasRecord($specParser->parse($aliasName, $root), $aliasName);
        }

        // If the user provides the --root parameter then we don't want to use
        // the site-set alias.
        $selectedRoot = $preflightArgs->selectedSite();
        if (!$selectedRoot) {
            $aliasName = $environment->getSiteSetAliasName();
            if (!empty($aliasName)) {
                $alias = $this->getAlias($aliasName);
                if ($alias) {
                    return $alias;
                }
            }
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
        // alias or site specification used by the user.
        return new AliasRecord(
            [
                'root' => $root,
                'uri' => $preflightArgs->uri(),
            ],
            '@self'
        );
    }
}
