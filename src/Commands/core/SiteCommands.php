<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\StructuredData\ListDataFromKeys;
use Robo\Common\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;

class SiteCommands extends DrushCommands implements SiteAliasManagerAwareInterface, ConfigAwareInterface
{
    use SiteAliasManagerAwareTrait;
    use ConfigAwareTrait;

    /**
     * Set a site alias that will persist for the current session.
     *
     * Stores the site alias being used in the current session in a temporary
     * file.
     *
     * @command site:set
     *
     * @param string $site Site specification to use, or "-" for previous site. Omit this argument to unset.
     *
     * @throws \Exception
     * @handle-remote-commands
     * @validate-php-extension posix
     * @usage drush site:set @dev
     *   Set the current session to use the @dev alias.
     * @usage drush site:set user@server/path/to/drupal#sitename
     *   Set the current session to use a remote site via site specification.
     * @usage drush site:set /path/to/drupal#sitename
     *   Set the current session to use a local site via site specification.
     * @usage drush site:set -
     *   Go back to the previously-set site (like `cd -`).
     * @usage drush site:set
     *   Without an argument, any existing site becomes unset.
     * @aliases use,site-set
     */
    public function siteSet($site = '@none')
    {
        $filename = $this->getConfig()->get('runtime.site-file-current');
        if ($filename) {
            $last_site_filename = $this->getConfig()->get('runtime.site-file-previous');
            if ($site == '-') {
                if (file_exists($last_site_filename)) {
                    $site = file_get_contents($last_site_filename);
                } else {
                    $site = '@none';
                }
            }
            if ($site == '@self') {
                // TODO: Add a method of SiteAliasManager to find a local
                // alias by directory / by env.cwd.
                //     $path = drush_cwd();
                //     $site_record = drush_sitealias_lookup_alias_by_path($path, true);
                $site_record = []; // This should be returned as an AliasRecord, not an array.
                if (isset($site_record['#name'])) {
                    $site = '@' . $site_record['#name']; // $site_record->name();
                } else {
                    $site = '@none';
                }
                // Using 'site:set @self' is quiet if there is no change.
                $current = is_file($filename) ? trim(file_get_contents($filename)) : "@none";
                if ($current == $site) {
                    return;
                }
            }
            // Alias record lookup exists.
            $aliasRecord = $this->siteAliasManager()->get($site);
            if ($aliasRecord) {
                if (file_exists($filename)) {
                    @unlink($last_site_filename);
                    @rename($filename, $last_site_filename);
                }
                $success_message = dt('Site set to @site', array('@site' => $site));
                if ($site == '@none' || $site == '') {
                    if (drush_delete_dir($filename)) {
                        $this->logger()->success(dt('Site unset.'));
                    }
                } elseif (drush_mkdir(dirname($filename), true)) {
                    if (file_put_contents($filename, $site)) {
                        $this->logger()->success($success_message);
                        $this->logger()->info(dt('Site information stored in @file', array('@file' => $filename)));
                    }
                }
            } else {
                throw new \Exception(dt('Could not find a site definition for @site.', array('@site' => $site)));
            }
        }
    }

    /**
     * Show site alias details, or a list of available site aliases.
     *
     * @command site:alias
     *
     * @param string $site Site alias or site specification.
     * @param array $options
     *
     * @return \Consolidation\OutputFormatters\StructuredData\ListDataFromKeys
     * @throws \Exception
     * @aliases sa
     * @usage drush site:alias
     *   List all alias records known to drush.
     * @usage drush site:alias @dev
     *   Print an alias record for the alias 'dev'.
     * @usage drush @none site-alias
     *   Print only actual aliases; omit multisites from the local Drupal installation.
     * @topics docs:aliases
     *
     */
    public function siteAlias($site = null, $options = ['format' => 'yaml'])
    {
        // Check to see if the user provided a specification that matches
        // multiple sites.
        $aliasList = $this->siteAliasManager()->getMultiple($site);
        if (is_array($aliasList)) {
            return new ListDataFromKeys($this->siteAliasExportList($aliasList, $options));
        }

        // Next check for a specific alias or a site specification.
        $aliasRecord = $this->siteAliasManager()->get($site);
        if ($aliasRecord !== false) {
            return new ListDataFromKeys([$aliasRecord->name() => $aliasRecord->export()]);
        }

        if ($site) {
            throw new \Exception('Site alias not found.');
        } else {
            $this->logger()->success('No site aliases found.');
        }
    }

    /**
     * Convert all legacy site alias files to new yml format.
     *
     * The new aliases.yml files shall be located in the same directory as the equivalent legacy file. Rerunning this
     * command is safe in that yml alias files are not overwritten, and legacy files are never changed/deleted.
     *
     * @command site:alias-convert
     * @usage drush site:alias-convert --simulate
     *   List the files to be converted but do not actually do anything.
     * @bootstrap max
     * @aliases sa-convert
     * @field-labels
     *   legacy: Legacy file
     *   disposition: Converted
     * @return RowsOfFields
     */
    public function siteAliasConvert()
    {
        /**
         * @todo
         *  - fix disposition column - needed data not yet present.
         *  - check search depth
         *  - support --simulate
         *  - review public/private class property changes
         *  - support custom paths instead of standard
         *  - mention this command in docs and examples
         *  - add a test
         *  - add logging which explains disposition of each file
         *  - suggest to user that she commits aliases to project after conversion
         *  - allow a custom destination to deal with read only filesystems
         */

        $config = Drush::config();
        $paths = [
            $config->get('drush.user-dir'),
            $config->get('drush.system-dir'),
        ];
        if ($siteRoot = Drush::bootstrapManager()->getRoot()) {
            $paths = array_merge($paths, [ dirname($siteRoot) . '/drush', "$siteRoot/drush", "$siteRoot/sites/all/drush" ]);
        }

        // Configure alias manager and convert all.
        $manager = $this->siteAliasManager();
        $manager->addSearchLocations($paths);
        $legacyFiles = $manager->legacyAliasConverter->discovery->findAllLegacyAliasFiles();
        $convertedFiles = $manager->legacyAliasConverter->convert();
        $rows = [];
        foreach ($legacyFiles as $legacyFile) {
            $rows[$legacyFile] = [
                'legacy' => $legacyFile,
                'disposition' => array_key_exists($legacyFile, $convertedFiles) ? 'Yes' : 'No',
            ];
        }
        return new RowsOfFields($rows);
    }

    /**
     * @param array $aliasList
     * @param $options
     * @return array
     */
    protected function siteAliasExportList($aliasList, $options)
    {
        $result = array_map(
            function ($aliasRecord) {
                return $aliasRecord->export();
            },
            $aliasList
        );
        return $result;
    }
}
