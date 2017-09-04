<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\SiteAlias\SiteAliasName;
use Consolidation\OutputFormatters\StructuredData\ListDataFromKeys;

class SiteCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Set a site alias to work on that will persist for the current session.
     *
     * @command site-set
     * @param $site Site specification to use, or "-" for previous site. Omit this argument to unset.
     * @handle-remote-commands
     * @validate-php-extension posix
     * @usage drush site-set @dev
     *   Set the current session to use the @dev alias.
     * @usage drush site-set user@server/path/to/drupal#sitename
     *   Set the current session to use a remote site via site specification.
     * @usage drush site-set /path/to/drupal#sitename
     *   Set the current session to use a local site via site specification.
     * @usage drush site-set -
     *   Go back to the previously-set site (like `cd -`).
     * @usage drush site-set
     *   Without an argument, any existing site becomes unset.
     * @aliases use
     * @complete \Drush\Commands\CompletionCommands::completeSiteAliases
     */
    public function siteSet($site = '@none', $options = ['a' =>'b'])
    {
        if ($filename = drush_sitealias_get_envar_filename()) {
            $last_site_filename = drush_sitealias_get_envar_filename('drush-drupal-prev-site-');
            if ($site == '-') {
                if (file_exists($last_site_filename)) {
                    $site = file_get_contents($last_site_filename);
                } else {
                    $site = '@none';
                }
            }
            if ($site == '@self') {
                $path = drush_cwd();
                $site_record = drush_sitealias_lookup_alias_by_path($path, true);
                if (isset($site_record['#name'])) {
                    $site = '@' . $site_record['#name'];
                } else {
                    $site = '@none';
                }
                // Using 'site-set @self' is quiet if there is no change.
                $current = is_file($filename) ? trim(file_get_contents($filename)) : "@none";
                if ($current == $site) {
                    return;
                }
            }
            if (_drush_sitealias_set_context_by_name($site)) {
                if (file_exists($filename)) {
                    @unlink($last_site_filename);
                    @rename($filename, $last_site_filename);
                }
                $success_message = dt("Site set to !site", array('!site' => $site));
                if ($site == '@none') {
                    if (drush_delete_dir($filename)) {
                        $this->logger()->success(dt('Site unset.'));
                    }
                } elseif (drush_mkdir(dirname($filename), true)) {
                    if (file_put_contents($filename, $site)) {
                        $this->logger()->success($success_message);
                        $this->logger()->info(dt("Site information stored in !file", array('!file' => $filename)));
                    }
                }
            } else {
                throw new \Exception(dt("Could not find a site definition for !site.", array('!site' => $site)));
            }
        }
    }

    /**
     * @hook init site-set
     */
    public function init()
    {
        // Try to get the @self alias to be defined.
        $phase = drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
    }

    /**
     * Show site alias details, or a list of available site aliases.
     *
     * @command site-alias
     * @param $site Site alias or site specification.
     * @option no-db Do not include the database record in the full alias record (default).
     * @option with-optional Include optional default items.
     * @option local-only Only display sites that are available on the local system (remote-site not set, and Drupal root exists)
     * @option show-hidden Include hidden internal elements in site alias output
     * @aliases sa
     * @usage drush site-alias
     *   List all alias records known to drush.
     * @usage drush site-alias @dev
     *   Print an alias record for the alias 'dev'.
     * @usage drush @none site-alias
     *   Print only actual aliases; omit multisites from the local Drupal installation.
     * @topics docs-aliases
     * @complete \Drush\Commands\CompletionCommands::completeSiteAliases
     *
     * @return \Consolidation\OutputFormatters\StructuredData\ListDataFromKeys
     */
    public function siteAlias($site = null, $options = ['format' => 'yaml'])
    {
        if (!$this->hasSiteAliasManager()) {
            return new ListDataFromKeys($this->oldSiteAliasCommandImplementation($site, $options));
        }

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

        $this->logger()->success('No sites found.');
    }

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

    protected function oldSiteAliasCommandImplementation($site, $options)
    {
        $site_list = $this->resolveSpecifications($site, $options);
        if ($site_list === false) {
            $this->logger()->success('No sites found.');
            return;
        }
        ksort($site_list);

        $site_specs = array();
        foreach ($site_list as $site => $alias_record) {
            $result_record = $this->prepareRecord($alias_record, $options);
            $site_specs[$site] = $result_record;
        }
        ksort($site_specs);
        return new ListDataFromKeys($site_specs);
    }

    /**
     * Prepare a site record for printing.
     *
     * @param alias_record
     *   The name of the site alias.
     */
    public function prepareRecord($alias_record, $options)
    {
        // Make sure that the default items have been added for all aliases
        _drush_sitealias_add_static_defaults($alias_record);

        // Include the optional items, if requested
        if ($options['with-optional']) {
            _drush_sitealias_add_transient_defaults($alias_record);
        }

        drush_sitealias_resolve_path_references($alias_record);

        // We don't want certain fields to go into the output
        if (!$options['show-hidden']) {
            foreach ($alias_record as $key => $value) {
                if ($key[0] == '#') {
                    unset($alias_record[$key]);
                }
            }
        }

        // We only want to output the 'root' item; don't output the '%root' path alias
        if (array_key_exists('path-aliases', $alias_record) && array_key_exists('%root', $alias_record['path-aliases'])) {
            unset($alias_record['path-aliases']['%root']);
            // If there is nothing left in path-aliases, then clear it out
            if (count($alias_record['path-aliases']) == 0) {
                unset($alias_record['path-aliases']);
            }
        }

        return $alias_record;
    }

    /**
     * Return a list of all site aliases known to Drush.
     *
     * The array key is the site alias name, and the array value
     * is the site specification for the given alias.
     */
    public static function siteAliasList()
    {
        return drush_get_context('site-aliases');
    }

    /**
     * Return a list of all of the local sites at the current Drupal root.
     *
     * The array key is the site folder name, and the array value
     * is the site specification for that site.
     */
    public static function siteSiteList()
    {
        $site_list = array();
        $base_path = Drush::bootstrapManager()->getRoot();
        if ($base_path) {
            $base_path .= '/sites';
            $files = drush_scan_directory($base_path, '/settings\.php/', array('.', '..', 'CVS', 'all'), 0, 1);
            foreach ($files as $filename => $info) {
                if ($info->basename == 'settings.php') {
                    $alias_record = drush_sitealias_build_record_from_settings_file($filename);
                    if (!empty($alias_record)) {
                        $site_list[drush_sitealias_uri_to_site_dir($alias_record['uri'])] = $alias_record;
                    }
                }
            }
        }
        return $site_list;
    }

    /**
     * Return the list of all site aliases and all local sites.
     */
    public static function siteAllList()
    {
        drush_sitealias_load_all();
        return array_merge(self::siteAliasList(), self::siteSiteList());
    }

    /**
     * Return the list of site aliases (remote or local) that the
     * user specified on the command line.  If none were specified,
     * then all are returned.
     */
    public function resolveSpecifications($specifications, $options)
    {
        $site_list = array();

        // Iterate over the arguments and convert them to alias records
        if (!empty($specifications)) {
            list($site_list, $not_found) = drush_sitealias_resolve_sitespecs($specifications);
            if (!empty($not_found)) {
                throw new \Exception(dt("Not found: @list", array("@list" => implode(', ', $not_found))));
            }
        } // If the user provided no args, then we will return everything.
        else {
            $site_list = self::siteAllList();

            // Filter out the hidden items.
            foreach ($site_list as $site_name => $one_site) {
                if (array_key_exists('#hidden', $one_site)) {
                    unset($site_list[$site_name]);
                }
            }

            // Remove leading @ for consistency.
            foreach ($site_list as $site_name => $one_site) {
                $site_list_new[ltrim($site_name, '@')] = $one_site;
            }
            $site_list = $site_list_new;
        }

        // Filter for only local sites if specified.
        if ($options['local-only']) {
            foreach ($site_list as $site_name => $one_site) {
                if ((array_key_exists('remote-site', $one_site)) ||
                (!array_key_exists('root', $one_site)) ||
                (!is_dir($one_site['root']))
                ) {
                    unset($site_list[$site_name]);
                }
            }
        }
        return $site_list;
    }
}
