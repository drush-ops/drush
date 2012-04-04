<?php

/**
 * Sync_enable adds options to sql-sync to enable and disable
 * modules after an sql-sync operation.  One use case for this
 * is to use Drush site aliases to automatically enable your 
 * development modules whenever you sync from your live site to
 * your dev site.
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
}
