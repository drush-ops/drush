<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Loader\ConfigProcessor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Discover alias files of two types:
 *
 * - sitename.alias.yml: contains multiple aliases, one for each of the
 *     environments of 'sitename'.
 * - group.aliases.yml: contains multiple aliases for all of the sites
 *     in the group 'group'. Each site can have multiple aliases for its
 *     environments.
 *
 * If an alais name is fully specified, with group, sitename and environment,
 * then Drush will load only the group alias file that contains the alias.
 * Otherwise, Drush will first search for the provided alias name in a
 * single-alias alias file. If no such file can be found, then it will try
 * all sitenames in all group alias files.
 */
class SiteAliasFileLoader
{
    public function __construct(SiteAliasFileDiscovery $discovery = null)
    {
        $this->discovery = $discovery ?: new SiteAliasFileDiscovery();
    }

    /**
     * Add a search location to our discovery object.
     *
     * @param string $path
     *
     * @return $this
     */
    public function addSearchLocation($path)
    {
        $this->discovery()->addSearchLocation($path);
        return $this;
    }

    /**
     * Return our discovery object.
     *
     * @return SiteAliasFileDiscovery
     */
    public function discovery()
    {
        return $this->discovery;
    }

    /**
     * Load the file containing the specified alias name.
     *
     * @param SiteAliasName $aliasName
     *
     * @return AliasRecord|false
     */
    public function load(SiteAliasName $aliasName)
    {
        // First attempt to load a sitename.alias.yml file for the alias.
        $aliasRecord = $this->loadSingleAliasFile($aliasName);
        if ($aliasRecord) {
            return $aliasRecord;
        }

        // If that didn't work, try a group.aliases.yml file.
        $aliasRecord = $this->loadNamedGroupAliasFile($aliasName);
        if ($aliasRecord) {
            return $aliasRecord;
        }

        // If we still haven't found the alias record, then search for
        // it in all of the group.aliases.yml and aliases.yml files.
        return $this->searchAllGroupAliasFiles($aliasName);
    }

    /**
     * If the alias name is '@sitename', or if it is '@sitename.env', then
     * look for a sitename.alias.yml file that contains it.
     *
     * @param SiteAliasName $aliasName
     *
     * @return AliasRecord|false
     */
    protected function loadSingleAliasFile(SiteAliasName $aliasName)
    {
        // Assume that the alias name is a @sitename.env if it is ambiguous.
        $aliasName->assumeAmbiguousIsSitename();

        // If the alias name includes a specific group name, then we must
        // load the alias from that group file; we therefore will not try
        // to find a single alias file that matches the sitename in that case.
        if ($aliasName->hasGroup()) {
            return false;
        }

        // Check to see if the appropriate sitename.alias.yml file can be
        // found. Return if it cannot.
        $path = $this->discovery()->findSingleSiteAliasFile($aliasName->sitename());
        if (!$path) {
            return false;
        }

        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }
        return $this->fetchAliasRecordFromSiteAliasData($aliasName, $data);
    }

    /**
     * If the alias name is '@group.sitename' or '@group.sitename.env',
     * then look for a group.aliases.yml file that contains 'sitename'.
     *
     * @param SiteAliasName $aliasName
     *
     * @return AliasRecord|false
     */
    protected function loadNamedGroupAliasFile(SiteAliasName $aliasName)
    {
        // Assume that the alias name is @group.sitename if it is ambiguous.
        $aliasName->assumeAmbiguousIsGroup();

        // If the alias name does not include a group component, then we
        // cannot search for a specific alias group file.
        if (!$aliasName->hasGroup()) {
            return false;
        }

        // Check to see if the appropriate group.aliases.yml file can be
        // found. Return if it cannot.
        $path = $this->discovery()->findGroupAliasFile($aliasName->group());
        if (!$path) {
            return false;
        }

        return $this->loadAliasRecordFromGroupAliasPath($aliasName, $path);
    }

    protected function loadAliasRecordFromGroupAliasPath(SiteAliasName $aliasName, $path)
    {
        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }
        $data = $this->fetchSiteAliasDataFromGroupAliasFile($aliasName, $data);
        if (!$data) {
            return false;
        }
        return $this->fetchAliasRecordFromSiteAliasData($aliasName, $data);
    }

    /**
     * If the alias name is '@sitename', or if it is '@sitename.env', and
     * there was not a sitename.alias.yml file found, then we must search
     * all available 'group.aliases.yml' and 'aliases.yml' files until we
     * find a matching alias.
     *
     * @param SiteAliasName $aliasName
     *
     * @return AliasRecord|false
     */
    protected function searchAllGroupAliasFiles(SiteAliasName $aliasName)
    {
        // Assume that the alias name is a @sitename.env if it is ambiguous.
        $aliasName->assumeAmbiguousIsSitename();

        // If the alias name definitely has a group, then it must be loaded
        // by 'loadNamedGroupAliasFile()', or not at all.
        if ($aliasName->hasGroup()) {
            return false;
        }

        $paths = $this->discovery()->findAllGroupAliasFiles();
        foreach ($paths as $path) {
            $aliasRecord = $this->loadAliasRecordFromGroupAliasPath($aliasName, $path);
            if ($aliasRecord) {
                return $aliasRecord;
            }
        }
        return false;
    }

    /**
     * Load the yaml contents of the specified file.
     *
     * @param string $path Path to file to load
     * @return array
     */
    protected function loadYml($path)
    {
        // TODO: Perhaps cache these alias files, as they may be read multiple times.
        return (array) Yaml::parse(file_get_contents($path));
    }

    /**
     * Given data loaded from a group alias file, return the data for the
     * sitename specified by the provided alias name, or 'false' if it does
     * not exist.
     *
     * @param SiteAliasName $aliasName the alias we are loading
     * @param array $data data from the group alias file
     * @return array|false
     */
    protected function fetchSiteAliasDataFromGroupAliasFile(SiteAliasName $aliasName, array $data)
    {
        $sitename = $aliasName->sitename();
        if (!isset($data['sites'][$sitename])) {
            return false;
        }
        return $data['sites'][$sitename];
    }

    /**
     * Given an array containing site alias data, return an alias record
     * containing the data for the requested record. If there is a 'common'
     * section, then merge that in as well.
     *
     * @param SiteAliasName $aliasName the alias we are loading
     * @param array $data
     *
     * @return AliasRecord|false
     */
    protected function fetchAliasRecordFromSiteAliasData(SiteAliasName $aliasName, array $data)
    {
        $env = $this->getEnvironmentName($aliasName, $data);
        if (!isset($data[$env])) {
            return false;
        }

        // Use a config processor to merge together the alias data
        $processor = new ConfigProcessor();
        if (isset($data['common'])) {
            $processor->add($data['common']);
        }
        $processor->add($data[$env]);

        // Export the combined data and create an AliasRecord object to manage it.
        return new AliasRecord($processor->export());
    }

    /**
     * Return the name of the environment requested.
     *
     * @param SiteAliasName $aliasName the alias we are loading
     * @param array $data
     *
     * @return string
     */
    protected function getEnvironmentName(SiteAliasName $aliasName, array $data)
    {
        // If the alias name specifically mentions the environment
        // to use, then return it.
        if ($aliasName->hasEnv()) {
            return $aliasName->env();
        }

        // If there is an entry named 'default', it will either contain the
        // name of the environment to use by default, or it will itself be
        // the default environment.
        if (isset($data['default'])) {
            return is_array($data['default']) ? 'default' : $data['default'];
        }

        // If there is an environment named 'dev', it will be our default.
        if (isset($data['dev'])) {
            return 'dev';
        }
        // If we don't know which environment to use, just take the first one.
        $keys = array_keys($data);
        return reset($keys);
    }
}
