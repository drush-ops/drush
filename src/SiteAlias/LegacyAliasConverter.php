<?php
namespace Drush\SiteAlias;

use Symfony\Component\Yaml\Yaml;
use Dflydev\DotAccessData\Data;
use Consolidation\SiteAlias\SiteAliasFileDiscovery;

/**
 * Find all legacy alias files and convert them to an equivalent '.yml' file.
 *
 * We will check the respective mod date of the legacy file and the generated
 * file, and update the generated file when the legacy file changes.
 */
class LegacyAliasConverter
{
    /**
     * @var SiteAliasFileDiscovery
     */
    protected $discovery;

    /**
     * @var string
     */
    protected $target;

    /**
     * @var boolean
     */
    protected $converted;

    /**
     * @var boolean
     */
    protected $simulate = false;

    /**
     * @var array
     */
    protected $convertedFileMap = [];

    /**
     * LegacyAliasConverter constructor.
     *
     * @param SiteAliasFileDiscovery $discovery Provide the same discovery
     *   object as used by the SiteAliasFileLoader to ensure that the same
     *   search locations are used for both classed.
     */
    public function __construct(SiteAliasFileDiscovery $discovery)
    {
        $this->discovery = $discovery;
        $this->target = '';
    }

    /**
     * @return bool
     */
    public function isSimulate()
    {
        return $this->simulate;
    }

    /**
     * @param bool $simulate
     */
    public function setSimulate($simulate)
    {
        $this->simulate = $simulate;
    }

    /**
     * @param string $target
     *   A directory to write to. If not provided, writes go into same dir as the corresponding legacy file.
     */
    public function setTargetDir($target)
    {
        $this->target = $target;
    }

    public function convertOnce()
    {
        if ($this->converted) {
            return;
        }
        return $this->convert();
    }

    public function convert()
    {
        $this->converted = true;
        $legacyFiles = $this->discovery->findAllLegacyAliasFiles();

        if (!$this->checkAnyNeedsConversion($legacyFiles)) {
            return [];
        }

        // We reconvert all legacy files together, because the aliases
        // in the legacy files might be written into multiple different .yml
        // files, depending on the naming conventions followed.
        $convertedFiles = $this->convertAll($legacyFiles);
        $this->writeAll($convertedFiles);

        return $convertedFiles;
    }

    protected function checkAnyNeedsConversion($legacyFiles)
    {
        foreach ($legacyFiles as $legacyFile) {
            $convertedFile = $this->determineConvertedFilename($legacyFile);
            if ($this->checkNeedsConversion($legacyFile, $convertedFile)) {
                return true;
            }
        }
        return false;
    }

    protected function convertAll($legacyFiles)
    {
        $result = [];
        foreach ($legacyFiles as $legacyFile) {
            $convertedFile = $this->determineConvertedFilename($legacyFile);
            $conversionResult = $this->convertLegacyFile($legacyFile);
            $result = static::arrayMergeRecursiveDistinct($result, $conversionResult);

            // If the conversion did not generate a similarly-named .yml file, then
            // make sure that one is created simply to record the mod date.
            if (!isset($result[$convertedFile])) {
                $result[$convertedFile] = [];
            }
        }
        return $result;
    }

    protected function writeAll($convertedFiles)
    {
        foreach ($convertedFiles as $path => $data) {
            $contents = $this->getContents($path, $data);

            // Write the converted file to the target directory
            // if a target directory was set.
            if (!empty($this->target)) {
                $path = $this->target . '/' . basename($path);
            }
            $this->writeOne($path, $contents);
        }
    }

    protected function getContents($path, $data)
    {
        if (!empty($data)) {
            $indent = 2;
            return Yaml::dump($data, PHP_INT_MAX, $indent, false, true);
        }

        $recoverSource = $this->recoverLegacyFile($path);
        if (!$recoverSource) {
            $recoverSource = 'the source alias file';
        }
        $contents = <<<EOT
# This is a placeholder file used to track when $recoverSource was converted.
# If you delete $recoverSource, then you may delete this file.
EOT;

        return $contents;
    }

    protected function writeOne($path, $contents)
    {
        $checksumPath = $this->checksumPath($path);
        if ($this->safeToWrite($path, $contents, $checksumPath)) {
            file_put_contents($path, $contents);
            $this->saveChecksum($checksumPath, $path, $contents);
        }
    }

    /**
     * Without any safeguards, the conversion process could be very
     * dangerous to users who modify their converted alias files (as we
     * would encourage them to do, if the goal is to convert!).
     *
     * This method determines whether it is safe to write to the converted
     * alias file at the specified path. If the user has modified the target
     * file, then we will not overwrite it.
     */
    protected function safeToWrite($path, $contents, $checksumPath)
    {
        // Bail if simulate mode is enabled.
        if ($this->isSimulate()) {
            return true;
        }

        // If the target file does not exist, it is always safe to write.
        if (!file_exists($path)) {
            return true;
        }

        // If the user deletes the checksum file, then we will never
        // overwrite the file again. This also covers potential collisions,
        // where the user might not realize that a legacy alias file
        // would write to a new site.yml file they created manually.
        if (!file_exists($checksumPath)) {
            return false;
        }

        // Read the data that exists at the target path, and calculate
        // the checksum of what exists there.
        $previousContents = file_get_contents($path);
        $previousChecksum = $this->calculateChecksum($previousContents);
        $previousWrittenChecksum = $this->readChecksum($checksumPath);

        // If the checksum of what we wrote before is the same as
        // the checksum we cached in the checksum file, then there has
        // been no user modification of this file, and it is safe to
        // overwrite it.
        return $previousChecksum == $previousWrittenChecksum;
    }

    public function saveChecksum($checksumPath, $path, $contents)
    {
        $name = basename($path);
        $comment = <<<EOT
# Checksum for converted Drush alias file $name.
# Delete this checksum file or modify $name to prevent further updates to it.
EOT;
        $checksum = $this->calculateChecksum($contents);
        @mkdir(dirname($checksumPath));
        file_put_contents($checksumPath, "{$comment}\n{$checksum}");
    }

    protected function readChecksum($checksumPath)
    {
        $checksumContents = file_get_contents($checksumPath);
        $checksumContents = preg_replace('/^#.*/m', '', $checksumContents);

        return trim($checksumContents);
    }

    protected function checksumPath($path)
    {
        return dirname($path) . '/.checksums/' . basename($path, '.yml') . '.md5';
    }

    protected function calculateChecksum($data)
    {
        return md5($data);
    }

    protected function determineConvertedFilename($legacyFile)
    {
        $convertedFile = preg_replace('#\.alias(|es)\.drushrc\.php$#', '.site.yml', $legacyFile);
        // Sanity check: if no replacement was done on the filesystem, then
        // we will presume that no conversion is needed here after all.
        if ($convertedFile == $legacyFile) {
            return false;
        }
        // If a target directory was set, then the converted file will
        // be written there. This will be done in writeAll(); we will strip
        // off everything except for the basename here. If no target
        // directory was set, then we will keep the path to the converted
        // file so that it may be written to the correct location.
        if (!empty($this->target)) {
            $convertedFile = basename($convertedFile);
        }
        $this->cacheConvertedFilePath($legacyFile, $convertedFile);
        return $convertedFile;
    }

    protected function cacheConvertedFilePath($legacyFile, $convertedFile)
    {
        $this->convertedFileMap[basename($convertedFile)] = basename($legacyFile);
    }

    protected function recoverLegacyFile($convertedFile)
    {
        if (!isset($this->convertedFileMap[basename($convertedFile)])) {
            return false;
        }
        return $this->convertedFileMap[basename($convertedFile)];
    }

    protected function checkNeedsConversion($legacyFile, $convertedFile)
    {
        // If determineConvertedFilename did not return a valid result,
        // then force no conversion.
        if (!$convertedFile) {
            return;
        }

        // Sanity check: the source file must exist.
        if (!file_exists($legacyFile)) {
            return false;
        }

        // If the target file does not exist, then force a conversion
        if (!file_exists($convertedFile)) {
            return true;
        }

        // We need to re-convert if the legacy file has been modified
        // more recently than the converted file.
        return filemtime($legacyFile) > filemtime($convertedFile);
    }

    protected function convertLegacyFile($legacyFile)
    {
        $aliases = [];
        $options = [];
        // Include the legacy file. In theory, this will define $aliases &/or $options.
        if (((@include $legacyFile) === false) || (!isset($aliases) && !isset($options))) {
            // TODO: perhaps we should log a warning?
            return;
        }

        // Decide whether this is a single-alias file or a multiple-alias file.
        if (preg_match('#\.alias\.drushrc\.php$#', $legacyFile)) {
            return $this->convertSingleAliasLegacyFile($legacyFile, $options ?: current($aliases));
        }
        return $this->convertMultipleAliasesLegacyFile($legacyFile, $aliases, $options);
    }

    protected function convertSingleAliasLegacyFile($legacyFile, $options)
    {
        $aliasName = basename($legacyFile, '.alias.drushrc.php');

        return $this->convertAlias($aliasName, $options, dirname($legacyFile));
    }

    protected function convertMultipleAliasesLegacyFile($legacyFile, $aliases, $options)
    {
        $result = [];
        foreach ($aliases as $aliasName => $data) {
            // 'array_merge' is how Drush 8 combines these records.
            $data = array_merge($options, $data);
            $convertedAlias = $this->convertAlias($aliasName, $data, dirname($legacyFile));
            $result = static::arrayMergeRecursiveDistinct($result, $convertedAlias);
        }
        return $result;
    }

    protected function convertAlias($aliasName, $data, $dir = '')
    {
        $env = 'dev';
        // We allow $aliasname to be:
        //   - sitename
        //   - sitename.env
        //   - group.sitename.env
        // In the case of the last, we will convert to
        // 'group-sitename.env' (and so on for any additional dots).
        // First, we will strip off the 'env' if it is present.
        if (preg_match('/(.*)\.([^.]+)$/', $aliasName, $matches)) {
            $aliasName = $matches[1];
            $env = $matches[2];
        }
        // Convert all remaining dots to dashes.
        $aliasName = strtr($aliasName, '.', '-');

        $data = $this->fixSiteData($data);

        return $this->convertSingleFileAlias($aliasName, $env, $data, $dir);
    }

    protected function fixSiteData($data)
    {
        $keyMap = $this->keyConversion();

        $options = [];
        foreach ($data as $key => $value) {
            if ($key[0] == '#') {
                unset($data[$key]);
            } elseif (!isset($keyMap[$key])) {
                $options[$key] = $data[$key];
                unset($data[$key]);
            }
        }
        ksort($options);

        foreach ($keyMap as $fromKey => $toKey) {
            if (isset($data[$fromKey]) && ($fromKey != $toKey)) {
                $data[$toKey] = $data[$fromKey];
                unset($data[$fromKey]);
            }
        }

        if (!empty($options)) {
            $data['options'] = $options;
        }
        if (isset($data['paths'])) {
            $data['paths'] = $this->removePercentFromKey($data['paths']);
        }
        ksort($data);

        return $this->remapData($data);
    }

    protected function remapData($data)
    {
        $converter = new Data($data);

        foreach ($this->dataRemap() as $from => $to) {
            if ($converter->has($from)) {
                $converter->set($to, $converter->get($from));
                $converter->remove($from);
            }
        }

        return $converter->export();
    }

    /**
     * Anything in the key of the returned array is converted
     * and written to a new top-level item in the result.
     *
     * Anything NOT identified by the key in the returned array
     * is moved to the 'options' element.
     */
    protected function keyConversion()
    {
        return [
            'remote-host' => 'host',
            'remote-user' => 'user',
            'root' => 'root',
            'uri' => 'uri',
            'path-aliases' => 'paths',
        ];
    }

    /**
     * This table allows for flexible remapping from one location
     * in the original alias to any other location in the target
     * alias.
     *
     * n.b. Most arbitrary data from the original alias will have
     * been moved into the 'options' element before this remapping
     * table is consulted.
     */
    protected function dataRemap()
    {
        return [
            'options.ssh-options' => 'ssh.options',
        ];
    }

    protected function removePercentFromKey($data)
    {
        return
            array_flip(
                array_map(
                    function ($item) {
                        return ltrim($item, '%');
                    },
                    array_flip($data)
                )
            );
    }

    protected function convertSingleFileAlias($aliasName, $env, $data, $dir = '')
    {
        $filename = $this->outputFilename($aliasName, '.site.yml', $dir);
        return [
            $filename => [
                $env => $data,
            ],
        ];
    }

    protected function outputFilename($name, $extension, $dir = '')
    {
        $filename = "{$name}{$extension}";
        // Just return the filename part if no directory was provided. Also,
        // the directoy is irrelevant if a target directory is set.
        if (empty($dir) || !empty($this->target)) {
            return $filename;
        }
        return "$dir/$filename";
    }

    /**
     * Merges arrays recursively while preserving.
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array
     *
     * @see http://php.net/manual/en/function.array-merge-recursive.php#92195
     * @see https://github.com/grasmash/bolt/blob/robo-rebase/src/Robo/Common/ArrayManipulator.php#L22
     */
    protected static function arrayMergeRecursiveDistinct(
        array &$array1,
        array &$array2
    ) {
        $merged = $array1;
        foreach ($array2 as $key => &$value) {
            $merged[$key] = self::mergeRecursiveValue($merged, $key, $value);
        }
        ksort($merged);
        return $merged;
    }

    /**
     * Process the value in an arrayMergeRecursiveDistinct - make a recursive
     * call if needed.
     */
    private static function mergeRecursiveValue(&$merged, $key, $value)
    {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            return self::arrayMergeRecursiveDistinct($merged[$key], $value);
        }
        return $value;
    }
}
