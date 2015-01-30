<?php

/**
 * @file
 * Example "Sync enable" sql-sync command alter.
 *
 * Sync_enable adds options to sql-sync to enable and disable
 * modules after an sql-sync operation.  One use case for this
 * is to use Drush site aliases to automatically enable your
 * development modules whenever you sync from your live site to
 * your dev site.  You may also add or remove permissions at
 * the same time.
 *
 * For example:
 *
 * @code
 * $aliases['dev'] = array (
 *   'root' => '/srv/www/drupal',
 *   'uri' => 'site.com',
 *   'target-command-specific' => array(
 *     'sql-sync'  => array(
 *       'enable'  => array('devel', 'hacked'),
 *       'disable' => array('securepages'),
 *       'permission' => array(
 *         'authenticated user' => array(
 *           'add' => array('access devel information', 'access environment indicator'),
 *           'remove' => 'change own password',
 *         ),
 *         'anonymous user' => array(
 *           'add' => 'access environment indicator',
 *         ),
 *       ),
 *     ),
 *   ),
 * );
 * @endcode
 *
 * To use this feature, copy the 'target-command-specific'
 * item from the example alias above, place it in your development
 * site aliases, and customize the development module list
 * to suit.  You must also copy the sync_enable.drush.inc
 * file to a location where Drush will find it, such as
 * $HOME/.drush.  See `drush topic docs-commands` for more
 * information.
 *
 * To set variables on a development site:
 *
 * Instead of calling variable_set and variable_delete in a post-sync
 * hook, consider adding $conf variables to settings.php.
 *
 * For example:
 *
 * $conf['error_level'] = 2;
 * error_reporting(E_ALL);
 * ini_set('display_errors', TRUE);
 * ini_set('display_startup_errors', TRUE);
 * $conf['preprocess_css'] = 0;
 * $conf['cache'] = 0;
 * $conf['googleanalytics_account'] = '';
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
function sync_enable_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sync') {
    $command['options']['enable']  = "Enable the specified modules in the target database after the sync operation has completed.";
    $command['options']['disable'] = "Disable the specified modules in the target database after the sync operation has completed.";
    $command['options']['permission'] = "Add or remove permissions from a role in the target database after the sync operation has completed. The value of this option must be an array, so it may only be specified in a site alias record or drush configuration file.  See `drush topic docs-example-sync-extension`.";
  }
}

/**
 * Implements drush_hook_post_COMMAND().
 *
 * The post hook is only called if the sql-sync operation completes
 * without an error.  When called, we check to see if the user specified
 * any modules to enable/disable.  If so, we will call pm-enable/pm-disable on
 * each module.
 */
function drush_sync_enable_post_sql_sync($source = NULL, $destination = NULL) {
  $modules_to_enable = drush_get_option_list('enable');
  if (!empty($modules_to_enable)) {
    drush_log(dt("Enable !modules post-sql-sync", array('!modules' => implode(',', $modules_to_enable))), 'ok');
    drush_invoke_process($destination, 'pm-enable', $modules_to_enable, array('yes' => TRUE));
  }
  $modules_to_disable = drush_get_option_list('disable');
  if (!empty($modules_to_disable)) {
    drush_log(dt("Disable !modules post-sql-sync", array('!modules' => implode(',', $modules_to_disable))), 'ok');
    drush_invoke_process($destination, 'pm-disable', $modules_to_disable, array('yes' => TRUE));
  }
  $permissions_table = drush_get_option('permission');
  if (!empty($permissions_table)) {
    foreach ($permissions_table as $role_name => $actions) {
      if (array_key_exists('add', $actions)) {
        $permissions_to_add = is_array($actions['add']) ? $actions['add'] : explode(', ', $actions['add']);
        foreach ($permissions_to_add as $permission) {
          $values = drush_invoke_process($destination, 'role-add-perm', array($role_name, $permission), array(), array('integrate' => TRUE));
        }
      }
      if (array_key_exists('remove', $actions)) {
        $permissions_to_remove = is_array($actions['remove']) ? $actions['remove'] : explode(', ', $actions['remove']);
        foreach ($permissions_to_remove as $permission) {
          $values = drush_invoke_process($destination, 'role-remove-perm', array($role_name, $permission), array(), array('integrate' => TRUE));
        }
      }
    }
  }
}
