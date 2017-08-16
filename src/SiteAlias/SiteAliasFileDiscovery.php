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
    protected $groupAliasFiles;
    protected $depth = '== 0';

    public function addSearchLocation($path)
    {
        $this->groupAliasFiles = null;
        if (is_dir($path)) {
            $this->searchLocations[] = $path;
        }
        return $this;
    }

    public function depth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

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

    public function findGroupAliasFile($groupName)
    {
        $groupAliasFileCache = $this->groupAliasFileCache();
        if (isset($groupAliasFileCache[$groupName])) {
            return $groupAliasFileCache[$groupName];
        }

        return false;
    }

    public function findAllGroupAliasFiles()
    {
        $unnamedGroupAliasFiles = $this->findUnnamedGroupAliasFiles();
        $groupAliasFileCache = $this->groupAliasFileCache();

        return array_merge($unnamedGroupAliasFiles, $groupAliasFileCache);
    }

    public function findAllSingleAliasFiles()
    {
        return $this->searchForAliasFiles('*.alias.yml');
    }

    protected function findUnnamedGroupAliasFiles()
    {
        if (empty($this->unknamedGroupAliasFiles)) {
            $this->unknamedGroupAliasFiles = $this->searchForAliasFiles('aliases.yml');
        }
        return $this->unknamedGroupAliasFiles;
    }

    protected function groupAliasFileCache()
    {
        if (!isset($this->groupAliasFiles)) {
            $this->groupAliasFiles = $this->searchForAliasFilesKeyedByBasenamePrefix('.aliases.yml');
        }
        return $this->groupAliasFiles;
    }

    protected function createFinder($searchPattern)
    {
        $finder = new Finder();
        $finder->files()
            ->name($searchPattern)
            ->in($this->searchLocations)
            ->depth($this->depth);
        return $finder;
    }

    protected function searchForAliasFiles($searchPattern)
    {
        $finder = $this->createFinder($searchPattern);
        $result = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $result[] = $path;
        }
        return $result;
    }

    protected function searchForAliasFilesKeyedByBasenamePrefix($filenameExensions)
    {
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

    protected function extractKey($basename, $filenameExensions)
    {
        return str_replace($filenameExensions, '', $basename);
    }
}
