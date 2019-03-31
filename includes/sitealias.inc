<?php

/**
 * @file
 * The site alias API.
 *
 * Run commands on remote server(s).
 * @see example.aliases.yml
 * @see http://drupal.org/node/670460
 */

use Drush\Commands\core\StatusCommands;
use Drush\Drush;
use Drush\Log\LogLevel;
use Consolidation\SiteAlias\SiteAlias;
use Webmozart\PathUtil\Path;

/**
 * Option keys used for site selection.
 */
function drush_sitealias_site_selection_keys() {
  return ['remote-host', 'remote-user', 'ssh-options', '#name', 'os'];
}

/**
 * Get a site alias record given an alias name or site specification.
 *
 * If it is the name of a site alias, return the alias record from
 * the site aliases array.
 *
 * If it is the name of a folder in the 'sites' folder, construct
 * an alias record from values stored in settings.php.
 *
 * If it is a site specification, construct an alias record from the
 * values in the specification.
 *
 * Site specifications come in several forms:
 * - /path/to/drupal#sitename
 * - user@server/path/to/drupal#sitename
 * - user@server/path/to/drupal            (sitename == server)
 * - user@server#sitename                  (only if $option['r'] set in some drushrc file on server)
 * - #sitename                             (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 * - sitename                              (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 *
 * Note that in the case of the first four forms, it is also possible
 * to add additional site variable to the specification using uri query
 * syntax.  For example:
 *
 *      user@server/path/to/drupal?db-url=...#sitename
 *
 * @param alias
 *   An alias name or site specification
 * @return array
 *   An alias record, or empty if none found.
 */
function drush_sitealias_get_record($alias, $alias_context = NULL) {
  // If legacy code is still looking for an alias record this way, redirect the
  // request to the alias manager.
  $alias_record = Drush::aliasManager()->get($alias);
  if (empty($alias_record)) {
    return [];
  }
  $config_record = $alias_record->exportConfig();
  $exported_config = $config_record->export();
  return isset($exported_config['options']) ? $exported_config['options'] : [];
}

/**
 * Determines whether a given site alias is for a remote site.
 *
 * @param string $alias
 *   An alias name or site specification.
 *
 * @return bool
 *   Returns TRUE if the alias refers to a remote site, FALSE if it does not, or NULL is unsure.
 */
function drush_sitealias_is_remote_site($alias) {
  if (is_array($alias) && !empty($alias['remote-host'])) {
    return TRUE;
  }
  if (!is_string($alias) || !strlen($alias)) {
    return NULL;
  }

  $site_record = drush_sitealias_get_record($alias);
  if ($site_record) {
    if (!empty($site_record['remote-host'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
  else {
    drush_set_error('Unrecognized site alias.');
  }
}

