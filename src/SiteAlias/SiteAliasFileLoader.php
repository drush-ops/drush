<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Loader\ConfigProcessor;
use Dflydev\DotAccessData\Util as DotAccessDataUtil;
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
    /**
     * @var SiteAliasFileDiscovery
     */
    protected $discovery;

    /**
     * SiteAliasFileLoader constructor
     *
     * @param SiteAliasFileDiscovery|null $discovery
     */
    public function __construct($discovery = null)
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
     * Return a list of all site aliases loadable from any findable path.
     *
     * @return AliasRecord[]
     */
    public function loadAll()
    {
        $result = [];
        $paths = $this->discovery()->findAllGroupAliasFiles();
        foreach ($paths as $path) {
            $result = array_merge($result, $this->loadAllRecordsFromGroupAliasPath($path));
        }
        $paths = $this->discovery()->findAllSingleAliasFiles();
        foreach ($paths as $path) {
            $aliasRecords = $this->loadSingleSiteAliasFileAtPath($path);
            foreach ($aliasRecords as $aliasRecord) {
                $this->storeAliasRecordInResut($result, $aliasRecord);
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Return a list of all available alias files. Does not include
     * legacy files.
     *
     * @return string[]
     */
    public function listAll()
    {
        return array_merge(
            $this->discovery()->findAllGroupAliasFiles(),
            $this->discovery()->findAllSingleAliasFiles()
        );
    }

    /**
     * Given an alias name that might represent multiple sites,
     * return a list of all matching alias records. If nothing was found,
     * or the name represents a single site + env, then we take
     * no action and return `false`.
     *
     * @param SiteAliasName $aliasName The alias name to look up.
     * @return AliasRecord[]|false
     */
    public function loadMultiple(SiteAliasName $aliasName)
    {
        // Is the provided alias name a fully qualified name
        // (`@group.site.env`)? If so, exit - we only load
        // groups of aliases here.
        $collectionName = $aliasName->couldBeCollectionName();
        if (!$collectionName) {
            return false;
        }

        // Look for a `group.aliases.yml` file that matches the requested name.
        $path = $this->discovery()->findGroupAliasFile($collectionName);
        $foundGroupAliasFile = !empty($path);

        // If we found a group alias file (group.aliases.yml), and there
        // is no sitename to modify the group name (that is, the alias
        // is simply `@group`, not `@group.site`), then return all of the
        // alias records we can find from @group.
        $sitename = $aliasName->sitenameOfGroupCollection();
        if ($foundGroupAliasFile && !$sitename) {
            return $this->loadAllRecordsFromGroupAliasPath($path);
        }

        // If we did NOT find a group file, then the alias must be
        // either `@site` or `@site.env`. If it is the
        if (!$foundGroupAliasFile && $aliasName->hasEnv()) {
            return false;
        }

        // Load the raw array of data from the specified path,
        // focusing down on the specific subset of data, if
        // applicable (e.g. if the alias was `@group.site`).
        $siteData = $this->getSiteDataForLoadMultiple($path, $sitename, $aliasName->sitename());
        if (!$siteData) {
            return false;
        }

        // Convert the raw array into a list of alias records.
        return $this->createAliasRecordsFromSiteData($aliasName, $siteData);
    }

    /**
     * @param array $siteData list of sites with its respective data
     *
     * @param SiteAliasName $aliasName The name of the record being created
     * @param $siteData An associative array of envrionment => site data
     * @return AliasRecord[]
     */
    protected function createAliasRecordsFromSiteData(SiteAliasName $aliasName, $siteData)
    {
        $result = [];
        $aliasName->assumeAmbiguousIsGroup();
        foreach ($siteData as $name => $data) {
            if (is_array($data)) {
                $aliasName->setEnv($name);

                $processor = new ConfigProcessor();
                $oneRecord = $this->fetchAliasRecordFromSiteAliasData($aliasName, $processor, $siteData);
                $this->storeAliasRecordInResut($result, $oneRecord);
            }
        }
        return $result;
    }

    /**
     * Given a path to an alias file, return multiple alias records for
     * some specific site and its environments. This will either extract
     * one site collection from a group alias file (group.aliases.yml), or
     * load all of a the data from a single site alias file (site.alias.yml)
     *
     * @param string $pathToGroup Location of file containing group data
     * @param string $sitenameInGroup If the alias is @group.sitename, then
     *   this parameter will hold the sitename.
     * @param string $singleSitename If the alias is @sitename or @sitename.env,
     *   then this parameter will hold the sitename.
     * @return array
     */
    protected function getSiteDataForLoadMultiple($pathToGroup, $sitenameInGroup, $singleSitename)
    {
        $siteData = $this->loadSiteDataFromGroup($pathToGroup, $sitenameInGroup);
        if ($siteData) {
            return $siteData;
        }
        $path = $this->discovery()->findSingleSiteAliasFile($singleSitename);
        if (!$path) {
            return false;
        }
        return $this->loadSiteDataFromPath($path);
    }

    /**
     * Store an alias record in a list. If the alias record has
     * a known name, then the key of the list will be the record's name.
     * Otherwise, append the record to the end of the list with
     * a numeric index.
     *
     * @param &AliasRecord[] $result list of alias records
     * @param AliasRecord $aliasRecord one more alias to store in the result
     */
    protected function storeAliasRecordInResut(&$result, AliasRecord $aliasRecord)
    {
        if (!$aliasRecord) {
            return;
        }
        $key = $aliasRecord->name();
        if (empty($key)) {
            $result[] = $aliasRecord;
            return;
        }
        $result[$key] = $aliasRecord;
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
        return $this->loadSingleAliasFileWithNameAtPath($aliasName, $path);
    }

    /**
     * Given only the path to an alias file `site.alias.yml`, return all
     * of the alias records for every environment stored in that file.
     *
     * @param string $path
     * @return AliasRecord[]
     */
    protected function loadSingleSiteAliasFileAtPath($path)
    {
        $aliasName = new SiteAliasName($this->siteNameFromPath($path));
        $siteData = $this->loadSiteDataFromPath($path);
        return $this->createAliasRecordsFromSiteData($aliasName, $siteData);
    }

    /**
     * Given the path to a group alias file `group.aliases.yml`, return
     * the `group` part.
     *
     * @param string $path
     * @return string
     */
    protected function groupNameFromPath($path)
    {
        // Return an empty string if there is no group.e
        if (basename($path) == 'aliases.yml') {
            return '';
        }

        return $this->basenameWithoutExtension($path, '.aliases.yml');
    }

    /**
     * Given the path to a single site alias file `site.alias.yml`,
     * return the `site` part.
     *
     * @param string $path
     */
    protected function siteNameFromPath($path)
    {
        return $this->basenameWithoutExtension($path, '.alias.yml');
    }

    /**
     * Chop off the `aliases.yml` or `alias.yml` part of a path. This works
     * just like `basename`, except it will throw if the provided path
     * does not end in the specified extension.
     *
     * @param string $path
     * @param string $extension
     * @return string
     * @throws \Exception
     */
    protected function basenameWithoutExtension($path, $extension)
    {
        $result = basename($path, $extension);
        // It is an error if $path does not end with alias.yml or aliases.yml, as appropriate
        if ($result == basename($path)) {
            throw new \Exception("$path must end with '$extension'");
        }
        return $result;
    }

    /**
     * Given an alias name and a path, load the data from the path
     * and process it as needed to generate the alias record.
     *
     * @param SiteAliasName $aliasName
     * @param string $path
     * @return AliasRecord|false
     */
    protected function loadSingleAliasFileWithNameAtPath(SiteAliasName $aliasName, $path)
    {
        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }
        $processor = new ConfigProcessor();
        return $this->fetchAliasRecordFromSiteAliasData($aliasName, $processor, $data);
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

    /**
     * Given a `group.aliases.yml` file, load all of the alias records
     * for all environments.
     *
     * @param string $path
     * @return AliasRecord[]
     */
    protected function loadAllRecordsFromGroupAliasPath($path)
    {
        $data = $this->loadYml($path);
        if (!$data || !isset($data['sites'])) {
            return [];
        }

        $names = array_keys($data['sites']);
        unset($names['common']);

        $group = $this->groupNameFromPath($path);

        $result = [];
        foreach ($names as $name) {
            $aliasName = new SiteAliasName($name);
            $aliasRecord = $this->fetchAliasRecordFromGroupAliasData($aliasName, $data, $group);
            $this->storeAliasRecordInResut($result, $aliasRecord);
        }
        return $result;
    }

    /**
     * Load a single alias from a group alias path. Pick the best default
     * environment if no environment name was specifically provided.
     *
     * @param SiteAliasName $aliasName
     * @param string $path
     * @return AliasRecord|false
     */
    protected function loadAliasRecordFromGroupAliasPath(SiteAliasName $aliasName, $path)
    {
        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }

        $group = $this->groupNameFromPath($path);

        return $this->fetchAliasRecordFromGroupAliasData($aliasName, $data, $group);
    }

    /**
     * Load the yml from the given path
     *
     * TODO: Maybe this could be removed and `loadYml` could be called directly.
     *
     * @param string $path
     * @return array
     */
    protected function loadSiteDataFromPath($path)
    {
        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }
        return $data;
    }

    /**
     * Given a path to a group alias file and the name of one entry in
     * that file, return the site data for that alias.
     *
     * @param string $path
     * @param string $sitenameInGroup
     * @return AliasRecord|false
     */
    protected function loadSiteDataFromGroup($path, $sitenameInGroup)
    {
        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }
        if (!isset($data['sites'][$sitenameInGroup])) {
            return false;
        }
        return $data['sites'][$sitenameInGroup];
    }

    /**
     * Given data from a `group.aliases.yml` file, look up and create
     * a single alias record.
     *
     * @param string $alaisName the name of the alias
     * @param array $data the data for the group file
     * @param string $group the group name from the filename
     */
    protected function fetchAliasRecordFromGroupAliasData($aliasName, $data, $group = '')
    {
        $processor = new ConfigProcessor();
        if (isset($data['common'])) {
            $processor->add($data['common']);
        }

        $siteData = $this->fetchSiteAliasDataFromGroupAliasFile($aliasName, $data);
        if (!$siteData) {
            return false;
        }

        return $this->fetchAliasRecordFromSiteAliasData($aliasName, $processor, $siteData, $group);
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
        if (empty($path)) {
            return [];
        }
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
    protected function fetchAliasRecordFromSiteAliasData(SiteAliasName $aliasName, ConfigProcessor $processor, array $data, $group = '')
    {
        $data = $this->adjustIfSingleAlias($data);
        $env = $this->getEnvironmentName($aliasName, $data);
        if (!$this->siteEnvExists($data, $env)) {
            return false;
        }

        // Add the 'common' section if it exists.
        if (isset($data['common']) && is_array($data['common'])) {
            $processor->add($data['common']);
        }

        // Then add the data from the desired environment.
        $processor->add($data[$env]);

        // Export the combined data and create an AliasRecord object to manage it.
        return new AliasRecord($processor->export(), '@' . $aliasName->sitename(), $env, $group);
    }

    /**
     * Determine whether there is a valid-looking environment '$env' in the
     * provided site alias data.
     *
     * @param array $data
     * @param string $env
     * @return bool
     */
    protected function siteEnvExists(array $data, $env)
    {
        return (
            is_array($data) &&
            isset($data[$env]) &&
            is_array($data[$env])
        );
    }

    /**
     * Adjust the alias data for a single-site alias. Usually, a .yml alias
     * file will contain multiple entries, one for each of the environments
     * of an alias. If there are no environments
     *
     * @param array $data
     * @return array
     */
    protected function adjustIfSingleAlias($data)
    {
        if (!$this->detectSingleAlias($data)) {
            return $data;
        }

        $result = [
            'default' => $data,
        ];

        return $result;
    }

    /**
     * A single-environment alias looks something like this:
     *
     *   ---
     *   root: /path/to/drupal
     *   uri: https://mysite.org
     *
     * A multiple-environment alias looks something like this:
     *
     *   ---
     *   default: dev
     *   dev:
     *     root: /path/to/dev
     *     uri: https://dev.mysite.org
     *   stage:
     *     root: /path/to/stage
     *     uri: https://stage.mysite.org
     *
     * The differentiator between these two is that the multi-environment
     * alias always has top-level elements that are associative arrays, and
     * the single-environment alias never does.
     *
     * @param array $data
     * @return array
     */
    protected function detectSingleAlias($data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && DotAccessDataUtil::isAssoc($value)) {
                return false;
            }
        }
        return true;
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
