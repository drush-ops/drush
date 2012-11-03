<?php

/**
 * The file_append_hook allows you to define file append
 * opperations that should happen after any Drush command.
 * Examples include:
 *
 * For example:
 *
 * core-rsync:  Append custom .htaccess modifications
 * after pushing code from stage to live.  In this scenario,
 * the custom directives are only necessary on the live
 * site, and are not stored in the vcs.
 *
 * $aliases['live'] = array (
 *   'root' => '/srv/www/drupal',
 *   'uri' => 'site.com',
 *   'target-command-specific' => array(
 *     'core-rsync'  => array(
 *       '.htaccess' => array(
 *         '#src' => '../htaccess-customizations',
 *       ),
 *     ),
 *   ),
 * );
 *
 * Alternate example:
 *
 * pm-updatecode: Append custom .htaccess modifications
 * after doing a minor update of Drupal core.
 *
 * $aliases['dev'] = array (
 *   'root' => '/srv/www/drupal',
 *   'uri' => 'site.com',
 *   'target-command-specific' => array(
 *     'pm-updatecode'  => array(
 *       '.htaccess' => array(
 *         '#src' => '../htaccess-customizations',
 *       ),
 *     ),
 *   ),
 * );
 *
 * To use this feature, copy the 'target-command-specific'
 * item from the example alias above, place it in your
 * site aliases, and customize the file list
 * to suit.  You must also copy the file_append_hook.drush.inc
 * file to a location where Drush will find it, such as
 * $HOME/.drush.  See `drush topic docs-commands` for more
 * information.
 */


/**
 * Implement hook drush_exit.
 *
 * The post hook is only called if the sql-sync operation completes
 * without an error.  When called, we check to see if the user specified
 * any modules to enable/disable.  If so, we will call pm-enable/pm-disable on each module.
 */
function file_append_hook_drush_exit() {
}
