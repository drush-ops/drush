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
 * If an alais name is fully specified, with group, sitename and environment,
 * then Drush will load only the group alias file that contains the alias.
 * Otherwise, Drush will first search for the provided alias name in a
 * single-alias alias file. If no such file can be found, then it will try
 * all sitenames in all group alias files.
 */
class SiteAliasFileDiscovery
{
    protected $searchLocations = [];
    protected $groupAliasFiles = [];
    protected $depth = '== 0';

    public function __consrtuct()
    {
    }

    public function addSearchLocation($path)
    {
        $this->searchLocations[] = $path;
        return $this;
    }

    public function depth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    public function findSingleSiteAliasFile($siteName)
    {
        $result = $this->searchForAliasFiles("$siteName.alias.yml");
        if (empty($result)) {
            return false;
        }
        return reset($result);
    }

    public function findGroupAliasFile($groupName)
    {
        return $this->searchForGroupAliasFile($$groupName);
    }

    public function findAllGroupAliasFiles()
    {
        $unnamedGroupAliasFiles = $this->findUnknamedGroupAliasFiles();
        $groupAliasFileCache = $this->groupAliasFileCache();

        return array_merge($unnamedGroupAliasFiles, $groupAliasFileCache);
    }

    protected function findUnknamedGroupAliasFiles()
    {
        if (empty($this->unknamedGroupAliasFiles)) {
            $this->unknamedGroupAliasFiles = $this->searchForAliasFiles('aliases.yml');
        }
        return $this->unknamedGroupAliasFiles;
    }

    protected function groupAliasFileCache()
    {
        if (!isset($this->$groupAliasFiles)) {
            $this->$groupAliasFiles = $this->searchForAliasFiles('*.aliases.yml');
        }
        return $this->$groupAliasFiles;
    }

    protected function searchForGroupAliasFile($name)
    {
        $groupAliasFileCache = $this->groupAliasFileCache();
        if (isset($groupAliasFileCache[$searchPattern][$name])) {
            return $groupAliasFileCache[$searchPattern][$name];
        }

        return false;
    }

    protected function searchForAliasFiles($searchPattern)
    {
        $result = [];
        foreach ($searchLocations as $dir) {
            $files = $this->searchForAliasFilesInLocation($searchPattern, $dir);
            $result = array_merge($files, $result);
        }
        return $result;
    }

    protected function searchForAliasFilesInLocation($searchPattern, $dir)
    {
        $finder = new Finder();
        $finder->files()
            ->name($searchPattern)
            ->in($dir)
            ->depth($this->depth);

        $result = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $key = $this->extractKey($file->getBasename(), $searchPattern);
            $result[$key] = $path;
        }
        return $result;
    }

    protected function extractKey($basename, $searchPattern)
    {
        $regex = str_replace('*', '([^\.]*)', $searchPattern);
        preg_match("/$regex/", $basename, $matches);
        return $matches[1];
    }
}
