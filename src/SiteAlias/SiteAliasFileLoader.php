<?php
namespace Drush\SiteAlias;

use Consolidation\Config\Loader\ConfigProcessor;
use Dflydev\DotAccessData\Util as DotAccessDataUtil;
use Drush\Internal\Config\Yaml\Yaml;

/**
 * Discover alias files:
 *
 * - sitename.site.yml: contains multiple aliases, one for each of the
 *     environments of 'sitename'.
 */
class SiteAliasFileLoader
{
    /**
     * @var SiteAliasFileDiscovery
     */
    protected $discovery;

    /**
     * @var array
     */
    protected $referenceData;

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
     * Allow configuration data to be used in replacements in the alias file.
     */
    public function setReferenceData($data)
    {
        $this->referenceData = $data;
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
        // First attempt to load a sitename.site.yml file for the alias.
        $aliasRecord = $this->loadSingleAliasFile($aliasName);
        if ($aliasRecord) {
            return $aliasRecord;
        }

        // If aliasname was provides as @site.env and we did not find it,
        // then we are done.
        if ($aliasName->hasSitename()) {
            return false;
        }

        // If $aliasName was provided as `@foo` and defaulted to `@self.foo`,
        // then make a new alias name `@foo.default` and see if we can find that.
        // Note that at the moment, `foo` is stored in $aliasName->env().
        $sitename = $aliasName->env();
        return $this->loadDefaultEnvFromSitename($sitename);
    }

    /**
     * Given only a site name, load the default environment from it.
     */
    protected function loadDefaultEnvFromSitename($sitename)
    {
        $path = $this->discovery()->findSingleSiteAliasFile($sitename);
        if (!$path) {
            return false;
        }
        $data = $this->loadSiteDataFromPath($path);
        if (!$data) {
            return false;
        }
        $env = $this->getDefaultEnvironmentName($data);

        $aliasName = new SiteAliasName($sitename, $env);
        $processor = new ConfigProcessor();
        return $this->fetchAliasRecordFromSiteAliasData($aliasName, $processor, $data);
    }

    /**
     * Return a list of all site aliases loadable from any findable path.
     *
     * @return AliasRecord[]
     */
    public function loadAll()
    {
        $result = [];
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
        return $this->discovery()->findAllSingleAliasFiles();
    }

    /**
     * Given an alias name that might represent multiple sites,
     * return a list of all matching alias records. If nothing was found,
     * or the name represents a single site + env, then we take
     * no action and return `false`.
     *
     * @param string $sitename The site name to return all environments for.
     * @return AliasRecord[]|false
     */
    public function loadMultiple($sitename)
    {
        if ($path = $this->discovery()->findSingleSiteAliasFile($sitename)) {
            if ($siteData = $this->loadSiteDataFromPath($path)) {
                // Convert the raw array into a list of alias records.
                return $this->createAliasRecordsFromSiteData($sitename, $siteData);
            }
        }
        return false;
    }

    /**
     * @param array $siteData list of sites with its respective data
     *
     * @param SiteAliasName $aliasName The name of the record being created
     * @param $siteData An associative array of envrionment => site data
     * @return AliasRecord[]
     */
    protected function createAliasRecordsFromSiteData($sitename, $siteData)
    {
        $result = [];
        if (!is_array($siteData) || empty($siteData)) {
            return $result;
        }
        foreach ($siteData as $envName => $data) {
            if (is_array($data)) {
                $aliasName = new SiteAliasName($sitename, $envName);

                $processor = new ConfigProcessor();
                $oneRecord = $this->fetchAliasRecordFromSiteAliasData($aliasName, $processor, $siteData);
                $this->storeAliasRecordInResut($result, $oneRecord);
            }
        }
        return $result;
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
     * look for a sitename.site.yml file that contains it.
     *
     * @param SiteAliasName $aliasName
     *
     * @return AliasRecord|false
     */
    protected function loadSingleAliasFile(SiteAliasName $aliasName)
    {
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
        $sitename = $this->siteNameFromPath($path);
        $siteData = $this->loadSiteDataFromPath($path);
        return $this->createAliasRecordsFromSiteData($sitename, $siteData);
    }

    /**
     * Given the path to a single site alias file `site.alias.yml`,
     * return the `site` part.
     *
     * @param string $path
     */
    protected function siteNameFromPath($path)
    {
        return $this->basenameWithoutExtension($path, '.site.yml');
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
        // It is an error if $path does not end with site.yml
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
        $data = $this->loadSiteDataFromPath($path);
        if (!$data) {
            return false;
        }
        $processor = new ConfigProcessor();
        return $this->fetchAliasRecordFromSiteAliasData($aliasName, $processor, $data);
    }

    /**
     * Load the yml from the given path
     *
     * @param string $path
     * @return array|bool
     */
    protected function loadSiteDataFromPath($path)
    {
        $data = $this->loadYml($path);
        if (!$data) {
            return false;
        }
        $selfSiteAliases = $this->findSelfSiteAliases($data);
        $data = array_merge($data, $selfSiteAliases);
        return $data;
    }

    /**
     * Given an array of site aliases, find the first one that is
     * local (has no 'host' item) and also contains a 'self.site.yml' file.
     * @param array $data
     * @return array
     */
    protected function findSelfSiteAliases($site_aliases)
    {
        foreach ($site_aliases as $site => $data) {
            if (!isset($data['host']) && isset($data['root'])) {
                foreach (['.', '..'] as $relative_path) {
                    $candidate = $data['root'] . '/' . $relative_path . '/drush/sites/self.site.yml';
                    if (file_exists($candidate)) {
                        return $this->loadYml($candidate);
                    }
                }
            }
        }
        return [];
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
        // TODO: Maybe use a YamlConfigLoader?
        return (array) Yaml::parse(file_get_contents($path));
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
    protected function fetchAliasRecordFromSiteAliasData(SiteAliasName $aliasName, ConfigProcessor $processor, array $data)
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
        return new AliasRecord($processor->export($this->referenceData), '@' . $aliasName->sitename(), $env);
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
     * @return bool
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
        return $this->getDefaultEnvironmentName($data);
    }

    /**
     * Given a data array containing site alias environments, determine which
     * envirionmnet should be used as the default environment.
     *
     * @param array $data
     * @return string
     */
    protected function getDefaultEnvironmentName(array $data)
    {
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
