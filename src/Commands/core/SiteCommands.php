<?php

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\LegacyAliasConverter;
use Consolidation\SiteAlias\SiteAliasFileDiscovery;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Filesystem\Path;

class SiteCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Set a site alias that will persist for the current session.
     *
     * Stores the site alias being used in the current session in a temporary
     * file.
     *
     * @command site:set
     *
     * @param string $site Site specification to use, or <info>-</info> for previous site. Omit this argument to unset.
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
    public function siteSet(string $site = '@none'): void
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
                $site_record = []; // This should be returned as an SiteAlias, not an array.
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
                $success_message = dt('Site set to @site', ['@site' => $site]);
                if ($site == '@none' || $site == '') {
                    if (drush_delete_dir($filename)) {
                        $this->logger()->success(dt('Site unset.'));
                    }
                } elseif (drush_mkdir(dirname($filename), true)) {
                    if (file_put_contents($filename, $site)) {
                        $this->logger()->success($success_message);
                        $this->logger()->info(dt('Site information stored in @file', ['@file' => $filename]));
                    }
                }
            } else {
                throw new \Exception(dt('Could not find a site definition for @site.', ['@site' => $site]));
            }
        }
    }

    /**
     * Show site alias details, or a list of available site aliases.
     *
     * @command site:alias
     *
     * @param string $site Site alias or site specification.
     *
     * @return UnstructuredListData
     * @throws \Exception
     * @aliases sa
     * @filter-default-field id
     * @usage drush site:alias
     *   List all alias records known to drush.
     * @usage drush site:alias @dev
     *   Print an alias record for the alias 'dev'.
     * @topics docs:aliases
     */
    public function siteAlias($site = null, array $options = ['format' => 'yaml'])
    {
        // First check to see if the user provided a specification that matches
        // multiple sites.
        $aliasList = $this->siteAliasManager()->getMultiple($site);
        if (is_array($aliasList) && !empty($aliasList)) {
            return new UnstructuredListData($this->siteAliasExportList($aliasList, $options));
        }

        // Next check for a specific alias or a site specification.
        $aliasRecord = $this->siteAliasManager()->get($site);
        if ($aliasRecord !== false) {
            return new UnstructuredListData([$aliasRecord->name() => $aliasRecord->export()]);
        }

        if ($site) {
            throw new \Exception('Site alias not found.');
        } else {
            $this->logger()->success('No site aliases found.');
        }
    }

    /**
     * Convert legacy site alias files to the new yml format.
     *
     * @command site:alias-convert
     * @param $destination An absolute path to a directory for writing new alias files.If omitted, user will be prompted.
     * @option sources A comma delimited list of paths to search. Overrides the default paths.
     * @usage drush site:alias-convert
     *   Find legacy alias files and convert them to yml. You will be prompted for a destination directory.
     * @usage drush site:alias-convert --simulate
     *   List the files to be converted but do not actually do anything.
     * @bootstrap max
     * @aliases sa-convert,sac
     * @topics docs:aliases
     */
    public function siteAliasConvert($destination, $options = ['format' => 'yaml', 'sources' => self::REQ]): array
    {
        /**
         * @todo
         *  - remove checksum system?
         */
        $config = $this->getConfig();
        if (!$paths = StringUtils::csvToArray($options['sources'])) {
            $paths = [
                $config->get('drush.user-dir'),
                $config->get('drush.system-dir'),
            ];
            if ($siteRoot = Drush::bootstrapManager()->getRoot()) {
                $paths = array_merge($paths, [ dirname($siteRoot) . '/drush', "$siteRoot/drush", "$siteRoot/sites/all/drush" ]);
            }
        }

        // Configure legacy converter.
        $discovery = new SiteAliasFileDiscovery();
        array_map([$discovery, 'addSearchLocation'], $paths);
        $discovery->depth('< 9');
        $legacyAliasConverter = new LegacyAliasConverter($discovery);
        $legacyAliasConverter->setTargetDir($destination);
        $legacyAliasConverter->setSimulate($this->getConfig()->simulate());

        // Find and convert.
        drush_mkdir($destination, true);
        $legacyFiles = $discovery->findAllLegacyAliasFiles();
        if ($convertedFiles = $legacyAliasConverter->convert()) {
            $args = ['!num' => count($convertedFiles), '!dest' => $destination];
            $message = dt('Created !num file(s) at !dest. Usually, one commits them to /drush/sites in your Composer project.', $args);
            $this->logger()->success($message);
        }

        $return = [
            'legacy_files' => $legacyFiles,
            'converted_files' => $convertedFiles,
        ];
        return $return;
    }

    /**
     * @hook interact site:alias-convert
     */
    public function interactSiteAliasConvert(Input $input, Output $output): void
    {
        if (!$input->getArgument('destination')) {
            $default = Path::join($this->getConfig()->home(), '.drush/sites');
            if ($composerRoot = Drush::bootstrapManager()->getComposerRoot()) {
                $default = Path::join($composerRoot, 'drush/sites');
            }
            $destination = $this->io()->ask('Absolute path to a directory for writing new alias files', $default);
            $input->setArgument('destination', $destination);
        }
    }

    /**
     * @param $options
     */
    protected function siteAliasExportList(array $aliasList, $options): array
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
