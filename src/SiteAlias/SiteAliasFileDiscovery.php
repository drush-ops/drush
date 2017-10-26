<?php
namespace Drush\SiteAlias;

use Symfony\Component\Finder\Finder;

/**
 * Discover alias files of two types:
 *
 * - sitename.alias.yml: contains multiple aliases, one for each of the
 *     environments of 'sitename'.
 * - group.aliases.yml: contains multiple aliases for all of the sites
 *     in the group 'group'. Each site can have multiple aliases for its
 *     environments.
 *
 * If an alias name is fully specified, with group, sitename and environment,
 * then Drush will load only the group alias file that contains the alias.
 * Otherwise, Drush will first search for the provided alias name in a
 * single-alias alias file. If no such file can be found, then it will try
 * all sitenames in all group alias files.
 */
class SiteAliasFileDiscovery
{
    protected $searchLocations = [];
    protected $groupAliasFiles;
    protected $depth = '== 0';

    /**
     * Add a location that alias files may be found.
     *
     * @param string $path
     * @return $this
     */
    public function addSearchLocation($path)
    {
        $this->groupAliasFiles = null;
        if (is_dir($path)) {
            $this->searchLocations[] = $path;
        }
        return $this;
    }

    /**
     * Set the search depth for finding alias files
     *
     * @param string|int $depth (@see \Symfony\Component\Finder\Finder::depth)
     * @return $this
     */
    public function depth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    /**
     * Find an alias file SITENAME.alias.yml in one
     * of the specified search locations.
     *
     * @param string $siteName
     * @return string|bool
     */
    public function findSingleSiteAliasFile($siteName)
    {
        $desiredFilename = "$siteName.alias.yml";
        foreach ($this->searchLocations as $dir) {
            $check = "$dir/$desiredFilename";
            if (file_exists($check)) {
                return $check;
            }
        }
        return false;
    }

    /**
     * Find a group alias file, GROUPNAME.aliases.yml, in
     * one of the specified search locations.
     *
     * @param string $groupName
     * @return string|bool
     */
    public function findGroupAliasFile($groupName)
    {
        $groupAliasFileCache = $this->groupAliasFileCache();
        if (isset($groupAliasFileCache[$groupName])) {
            return $groupAliasFileCache[$groupName];
        }

        return false;
    }

    /**
     * Return a list of all GROUPNAME.aliases.yml files in any
     * of the search locations.
     *
     * @return string[]
     */
    public function findAllGroupAliasFiles()
    {
        $unnamedGroupAliasFiles = $this->findUnnamedGroupAliasFiles();
        $groupAliasFileCache = $this->groupAliasFileCache();

        return array_merge($unnamedGroupAliasFiles, $groupAliasFileCache);
    }

    /**
     * Return a list of all SITENAME.alias.yml files in any of
     * the search locations.
     *
     * @return string[]
     */
    public function findAllSingleAliasFiles()
    {
        return $this->searchForAliasFiles('*.alias.yml');
    }

    /**
     * Return all of the legacy alias files used in previous Drush versions.
     *
     * @return string[]
     */
    public function findAllLegacyAliasFiles()
    {
        return array_merge(
            $this->searchForAliasFiles('*.alias.drushrc.php'),
            $this->searchForAliasFiles('*.aliases.drushrc.php')
        );
    }

    /**
     * Return a list of all aliases.yml alias files.
     *
     * @return string[]
     */
    protected function findUnnamedGroupAliasFiles()
    {
        if (empty($this->unknamedGroupAliasFiles)) {
            $this->unknamedGroupAliasFiles = $this->searchForAliasFiles('aliases.yml');
        }
        return $this->unknamedGroupAliasFiles;
    }

    /**
     * Prime the cache of all GROUPNAME.aliases.yml files.
     *
     * @return string[]
     */
    protected function groupAliasFileCache()
    {
        if (!isset($this->groupAliasFiles)) {
            $this->groupAliasFiles = $this->searchForAliasFilesKeyedByBasenamePrefix('.aliases.yml');
        }
        return $this->groupAliasFiles;
    }

    /**
     * Create a Symfony Finder object to search all available search locations
     * for the specified search pattern.
     *
     * @param string $searchPattern
     * @return Finder
     */
    protected function createFinder($searchPattern)
    {
        $finder = new Finder();
        $finder->files()
            ->name($searchPattern)
            ->in($this->searchLocations)
            ->depth($this->depth);
        return $finder;
    }

    /**
     * Return a list of all alias files matching the provided pattern.
     *
     * @param string $searchPattern
     * @return string[]
     */
    protected function searchForAliasFiles($searchPattern)
    {
        if (empty($this->searchLocations)) {
            return [];
        }
        $finder = $this->createFinder($searchPattern);
        $result = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $result[] = $path;
        }
        return $result;
    }

    /**
     * Return a list of all alias files with the specified extension.
     *
     * @param string $filenameExensions
     * @return string[]
     */
    protected function searchForAliasFilesKeyedByBasenamePrefix($filenameExensions)
    {
        if (empty($this->searchLocations)) {
            return [];
        }
        $searchPattern = '*' . $filenameExensions;
        $finder = $this->createFinder($searchPattern);
        $result = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $key = $this->extractKey($file->getBasename(), $filenameExensions);
            $result[$key] = $path;
        }
        return $result;
    }

    // TODO: Seems like this could just be basename()
    protected function extractKey($basename, $filenameExensions)
    {
        return str_replace($filenameExensions, '', $basename);
    }
}
