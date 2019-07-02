<?php

/**
 * @file
 * Example policy commandfile. Modify as desired.
 *
 * Validates commands as they are issued and returns an error
 * or changes options when policy is violated.
 *
 * You can copy this file to any of the following
 *   1. A .drush folder in your HOME folder.
 *   2. Anywhere in a folder tree below an active module on your site.
 *   3. /usr/share/drush/commands (configurable)
 *   4. In an arbitrary folder specified with the --include option.
 *   5. Drupal's /drush or sites/all/drush folder, or in the /drush
 *        folder in the directory above the Drupal root (note: sql-sync
 *        validation won't work in any of these locations).
 */

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * Prevent catastrophic braino. Note that this file has to be local to the
 * machine that intitiates sql-sync command.
 */
function drush_policy_sql_sync_validate($source = NULL, $destination = NULL) {
  if ($destination == '@prod') {
    return drush_set_error('POLICY_DENY', dt('Per examples/policy.drush.inc, you may never overwrite the production database.'));
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * We can also limit rsync operations to production sites.
 */
function drush_policy_core_rsync_validate($source = NULL, $destination = NULL) {
  if (preg_match("/^@prod/", $destination)) {
    return drush_set_error('POLICY_DENY', dt('Per examples/policy.drush.inc, you may never rsync to the production site.'));
  }
}

/**
 * Implements hook_drush_sitealias_alter
 *
 * Alter alias record data in code.
 */
function policy_drush_sitealias_alter(&$alias_record) {
  // A duplicate of the old implementation of the 'parent' element.
  // Keep this if you want to keep using 'parent', but do not want
  // to be nagged (or worse, break when it is removed).
  if (isset($alias_record['parent'])) {
    // Fetch and merge in each parent
    foreach (explode(',', $alias_record['parent']) as $parent) {
      $parent_record = drush_sitealias_get_record($parent);
      unset($parent_record['#name']);
      unset($parent_record['#file']);
      unset($parent_record['#hidden']);
      $array_based_keys = array_merge(drush_get_special_keys(), array('path-aliases'));
      foreach ($array_based_keys as $array_based_key) {
        if (isset($alias_record[$array_based_key]) && isset($parent_record[$array_based_key])) {
          $alias_record[$array_based_key] = array_merge($parent_record[$array_based_key], $alias_record[$array_based_key]);
        }
      }
      $alias_record = array_merge($parent_record, $alias_record);
    }
    unset($alias_record['parent']);
  }

  // If the alias is a remote wildcard alias, then test to see if the
  // environment exists, and raise an "environment not found" error if
  // it does not.
  if (isset($alias_record['remote-host'])) {
    $host = $alias_record['remote-host'];
    if (preg_match('#\.myserver.com$#', $host)) {
      $ip = gethostbyname($host);
      // If the return value of gethostbyname equals its input parameter,
      // that indicates failure.
      if ($host == $ip) {
        $aliasName = $alias_record['#name'];
        return drush_set_error('NO_SUCH_ALIAS', "The alias $aliasName refers to a multidev environment that does not exist.");
      }
    }
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * Encourage folks to use `composer` instead of Drush pm commands
 */
function drush_policy_pm_updatecode_validate() {
  return _deny_message();
}

function drush_policy_pm_update_validate() {
  return _deny_message();
}

function drush_policy_pm_download_validate() {
  return _deny_message();
}

function _deny_message() {
  if (!drush_get_option('pm-force')) {
    $msg = 'This codebase is assembled with Composer instead of Drush. Use `composer update` and `composer require` instead of `drush pm-updatecode` and `drush pm-download`. You may override this error by using the --pm-force option.';
    return drush_set_error('POLICY_PM_DENY', dt($msg));
  }
}

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
function policy_drush_help_alter($command) {
  if ($command['command'] == 'updatedb') {
    $command['options']['token'] = 'Per site policy, you must specify a token in the --token option for all commands.';
  }
  elseif (in_array($command['command'], array('pm-updatecode', 'pm-update', 'pm-download'))) {
    $command['options']['pm-force'] = 'Override site policy and allow Drush codebase management (pm-* commands)';
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * To test this example without copying, execute
 * `drush --include=./examples updatedb` from within your drush directory.
 *
 * Unauthorized users may view pending updates but not execute them.
 */
function drush_policy_updatedb_validate() {
  // Check for a token in the request. In this case, we require --token=secret.
  if (!drush_get_option('token') == 'secret') {
    drush_log(dt('Per site policy, you must add a secret --token complete this command. See examples/policy.drush.inc.  If you are running a version of drush prior to 4.3 and are not sure why you are seeing this message, please see http://drupal.org/node/1024824.'), 'warning');
    drush_set_context('DRUSH_AFFIRMATIVE', FALSE);
    drush_set_context('DRUSH_NEGATIVE', TRUE);
  }
}

/**
 * Implements drush_hook_COMMAND_validate().
 *
 * Only sudo tells me to make a sandwich: http://xkcd.com/149/
 */
function drush_policy_make_me_a_sandwich_validate() {
  if (drush_is_windows()) {
    // $name = drush_get_username();
    // TODO: implement check for elevated process using w32api
    // as sudo is not available for Windows
    // @see http://php.net/manual/en/book.w32api.php
    // @see http://social.msdn.microsoft.com/Forums/en/clr/thread/0957c58c-b30b-4972-a319-015df11b427d
  }
  else {
    $name = posix_getpwuid(posix_geteuid());
    if ($name['name'] !== 'root') {
      return drush_set_error('POLICY_MAKE_IT_YOUSELF', dt('What? Make your own sandwich.'));
    }
  }
}
