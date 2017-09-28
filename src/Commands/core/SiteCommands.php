<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

use Drush\Drush;
use Drush\SiteAlias\AliasRecord;
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
     * @command site:set
     * @param $site Site specification to use, or "-" for previous site. Omit this argument to unset.
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
     * @hidden
     */
    public function siteSet($site = '@none', $options = ['a' =>'b'])
    {
        // @todo Needs modernizing to get it functional, so @hidden for now.

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
                $site_record = []; // drush_sitealias_lookup_alias_by_path($path, true);
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
            if (false && _drush_sitealias_set_context_by_name($site)) {
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
     * Show site alias details, or a list of available site aliases.
     *
     * @command site:alias
     * @param $site Site alias or site specification.
     * @aliases sa
     * @usage drush site:alias
     *   List all alias records known to drush.
     * @usage drush site:alias @dev
     *   Print an alias record for the alias 'dev'.
     * @usage drush @none site-alias
     *   Print only actual aliases; omit multisites from the local Drupal installation.
     * @topics docs:aliases
     *
     * @return \Consolidation\OutputFormatters\StructuredData\ListDataFromKeys
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
