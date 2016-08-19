<?php

/**
 * @file
 * Example "Sync via HTTP" sql-sync command alter.
 *
 * Sync_via_http allows you to sql-sync your database using HTTP
 * (e.g. wget or curl) instead of rsync.  This is helpful for
 * exporting your database to colaborators without shell access
 * to the production or staging server.
 *
 * For example:
 *
 * @code
 * $aliases['staging'] = array (
 *   'root' => '/srv/www/drupal',
 *   'uri' => 'staging.site.com',
 *   'source-command-specific' => array(
 *     'sql-sync'  => array(
 *       'http-sync'  => 'https://staging.site.com/protected-directory/site-database-dump.sql',
 *       'http-sync-user' => 'wwwadmin',
 *       'http-sync-password' => 'secretsecret',
 *     ),
 *   ),
 * );
 * @endcode
 *
 * To use this feature, copy the 'source-command-specific'
 * item from the example alias above, place it in your staging
 * site aliases, and custom the access credentials as
 * necessary.  You must also copy the sync_via_http.drush.inc
 * file to a location where Drush will find it, such as
 * $HOME/.drush.  See `drush topic docs-commands` for more
 * information.
 *
 * IMPORTANT NOTE:  This example does not cause the sql dump
 * to be performed; it is presumed that the dump file already
 * exists at the provided URL.  For a full solution, a web page
 * that initiated an sql-dump (or perhaps a local sql-sync followed
 * by an sql-sanitize and then an sql-dump) would be necessary.
 */

/**
 * Implements hook_drush_help_alter().
 *
 * When a hook extends a command with additional options, it must
 * implement help alter and declare the option(s).  Doing so will add
 * the option to the help text for the modified command, and will also
 * allow the new option to be specified on the command line.  Without
 * this, Drush will fail with an error when a user attempts to use
 * the option.
 */
function sync_via_http_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sync') {
    $command['options']['http-sync']  = "Copy the database via http instead of rsync.  Value is the url that the existing database dump can be found at.";
    $command['sub-options']['http-sync']['http-sync-user']  = "Username for the protected directory containing the sql dump.";
    $command['sub-options']['http-sync']['http-sync-password']  = "Password for the same directory.";
  }
}

/**
 * Implements drush_hook_pre_COMMAND().
 *
 * During the pre hook, determine if the http-sync option has been
 * specified.  If it has been, then disable the normal ssh + rsync
 * dump-and-transfer that sql-sync usually does, and transfer the
 * database dump via an http download.
 */
function drush_sync_via_http_pre_sql_sync($source = NULL, $destination = NULL) {
  $sql_dump_download_url = drush_get_option('http-sync');
  if (!empty($sql_dump_download_url)) {
    $user = drush_get_option('http-sync-user', FALSE);
    $password = drush_get_option('http-sync-password', FALSE);
    $source_dump_file = _drush_sync_via_http_download_file($sql_dump_download_url, $user, $password);
    if ($source_dump_file === FALSE) {
      return drush_set_error('DRUSH_CANNOT_DOWNLOAD', dt("The URL !url could not be downloaded.", array('!url' => $sql_dump_download_url)));
    }
    drush_set_option('target-dump', $source_dump_file);
    drush_set_option('no-dump', TRUE);
    drush_set_option('no-sync', TRUE);
  }
}

/**
 * Downloads a files.
 *
 * Optionaly uses user authentication, using either wget or curl, as available.
 */
function _drush_sync_via_http_download_file($url, $user = FALSE, $password = FALSE, $destination = FALSE, $overwrite = TRUE) {
  static $use_wget;
  if ($use_wget === NULL) {
    $use_wget = drush_shell_exec('which wget');
  }

  $destination_tmp = drush_tempnam('download_file');
  if ($use_wget) {
    if ($user && $password) {
      drush_shell_exec("wget -q --timeout=30 --user=%s --password=%s -O %s %s", $user, $password, $destination_tmp, $url);
    }
    else {
      drush_shell_exec("wget -q --timeout=30 -O %s %s", $destination_tmp, $url);
    }
  }
  else {
    if ($user && $password) {
      drush_shell_exec("curl -s -L --connect-timeout 30 --user %s:%s -o %s %s", $user, $password, $destination_tmp, $url);
    }
    else {
      drush_shell_exec("curl -s -L --connect-timeout 30 -o %s %s", $destination_tmp, $url);
    }
  }
  if (!drush_get_context('DRUSH_SIMULATE')) {
    if (!drush_file_not_empty($destination_tmp) && $file = @file_get_contents($url)) {
      @file_put_contents($destination_tmp, $file);
    }
    if (!drush_file_not_empty($destination_tmp)) {
      // Download failed.
      return FALSE;
    }
  }
  if ($destination) {
    drush_move_dir($destination_tmp, $destination, $overwrite);
    return $destination;
  }
  return $destination_tmp;
}
