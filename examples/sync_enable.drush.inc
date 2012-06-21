<?php

/**
 * Sync_enable adds options to sql-sync to enable and disable
 * modules after an sql-sync operation.  One use case for this
 * is to use Drush site aliases to automatically enable your
 * development modules whenever you sync from your live site to
 * your dev site.  You may also add or remove permissions at
 * the same time.
 *
 * For example:
 *
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
 *
 * To use this feature, copy the 'target-command-specific'
 * item from the example alias above, place it in your development
 * site aliases, and customize the development module list
 * to suit.  You must also copy the sync_enable.drush.inc
 * file to a location where Drush will find it, such as
 * $HOME/.drush.  See `drush topic docs-commands` for more
 * information.
 */

/**
 * Implement hook help alter.
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
 * Implement hook post sql sync.
 *
 * The post hook is only called if the sql-sync operation completes
 * without an error.  When called, we check to see if the user specified
 * any modules to enable/disable.  If so, we will call pm-enable/pm-disable on each module.
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
    foreach ($permissions_table as $role => $actions) {
      $values = drush_invoke_process($destination, 'sql-query', array("select perm from permission, role where role.name='$role' and role.rid = permission.rid;"), array(), array('integrate' => FALSE));
      // Remove the first line and explode
      $sql_output = preg_replace('/^.+\n/', '', trim($values['output']));
      $permissions = explode(', ', $sql_output);
      $original_permissions = $permissions;
      if (array_key_exists('add', $actions)) {
        $permissions_to_add = is_array($actions['add']) ? $actions['add'] : explode(', ', $actions['add']);
        drush_log(dt("Add !permissions to !role post-sql-sync", array('!permissions' => implode(', ', $permissions_to_add), '!role' => $role)), 'ok');
        $permissions = array_unique(array_merge($permissions, $permissions_to_add));
      }
      if (array_key_exists('remove', $actions)) {
        $permissions_to_remove = is_array($actions['remove']) ? $actions['remove'] : explode(', ', $actions['remove']);
        drush_log(dt("Remove !permissions from !role post-sql-sync", array('!permissions' => implode(', ', $permissions_to_remove), '!role' => $role)), 'ok');
        $permissions = array_diff($permissions, $permissions_to_remove);
      }
      if ($permissions != $original_permissions) {
        $permissions_string = implode(', ', $permissions);
        $values = drush_invoke_process($destination, 'sql-query', array("update permission, role set perm='$permissions_string' where role.name='$role' and role.rid = permission.rid;"), array(), array('integrate' => FALSE));
      }
    }
  }
}
