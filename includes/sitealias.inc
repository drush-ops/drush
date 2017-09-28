<?php

/**
 * @file
 * The site alias API.
 *
 * Run commands on remote server(s).
 * @see example.aliases.drushrc.php
 * @see http://drupal.org/node/670460
 */

use Drush\Commands\core\StatusCommands;
use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\SiteAlias\AliasRecord;
use Webmozart\PathUtil\Path;



/**
 * Option keys used for site selection.
 */
function drush_sitealias_site_selection_keys() {
  return array('remote-host', 'remote-user', 'ssh-options', '#name', 'os');
}

/**
 * Returns the filename for the file that stores the DRUPAL_SITE variable.
 *
 * @param string $filename_prefix
 *   An arbitrary string to prefix the filename with.
 *
 * @return string|false
 *   Returns the full path to temp file if possible, or FALSE if not.
 */
function drush_sitealias_get_envar_filename($filename_prefix = 'drush-drupal-site-') {
  $shell_pid = getenv('DRUSH_SHELL_PID');
  if (!$shell_pid && function_exists('posix_getppid')) {
    $shell_pid = posix_getppid();
  }
  if (!$shell_pid) {
    return FALSE;
  }

  $tmp = getenv('TMPDIR') ? getenv('TMPDIR') : '/tmp';
  $username = drush_get_username();

  return "{$tmp}/drush-env-{$username}/{$filename_prefix}" . $shell_pid;
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
 * Decide on which side to run a core-rsync.
 *
 * @param $source
 * @param $destination
 * @param $runner Where to run the rsync operation: 'destination', 'source',
 *   'auto' ('destination' if both are remote, otherwise '@self') or FALSE (@self)
 * @return mixed
 */
function drush_get_runner($source, $destination, $runner = FALSE) {
  if (is_string($source)) {
    $source = drush_sitealias_get_record($site);
  }
  if (is_string($destination)) {
    $destination = drush_sitealias_get_record($destination);
  }

  // If both sites are remote, and --runner=auto, then we'll use the destination site.
  if (drush_sitealias_is_remote_site($source) && drush_sitealias_is_remote_site($destination)) {
    if ($runner == 'auto') {
      $runner = 'destination';
    }
  }

  // If the user explicitly requests a remote site, then return the selected one.
  if ($runner == 'destination') {
    return "@" . $destination['#name'];
  }
  if ($runner == 'source') {
    return "@" . $source['#name'];
  }

  // Default to running rsync locally. When in doubt, local is best, because
  // we can always resolve aliases here.
  return '@self';
}
