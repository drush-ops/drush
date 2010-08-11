<?php
// $Id$

/**
 * @file
 * Documentation of the Drush API.
 *
 * All drush commands are invoked in a specific order, using
 * drush-made hooks, very similar to the Drupal hook system. See drush_invoke()
 * for the actual implementation.
 *
 * For any command named "hook", the following hooks are called, in
 * order:
 *
 * 1. drush_hook_COMMAND_validate()
 * 2. drush_hook_pre_COMMAND()
 * 3. drush_hook_COMMAND()
 * 4. drush_hook_post_COMMAND()
 *
 * For example, here are the hook opportunities for a mysite.drush.inc file
 * that wants to hook into the `pm-download` command.
 *
 * 1. drush_mysite_pm_download_validate()
 * 2. drush_mysite_pre_pm_download()
 * 3. drush_mysite_pm_download()
 * 4. drush_mysite_post_pm_download()
 *
 * If any of those fails, the rollback mechanism is called. It will
 * call, in reverse, all _rollback hooks. The mysite command file can implement
 * the following rollback hooks:
 *
 * 1. drush_mysite_post_pm_download_rollback()
 * 2. drush_mysite_pm_download_rollback()
 * 3. drush_mysite_pre_pm_download_rollback()
 * 4. drush_mysite_pm_download_validate_rollback()
 *
 * Before any command is called, hook_drush_init() is also called.
 * hook_drush_exit() is called at the very end of command invocation.
 *
 * @see includes/command.inc
 *
 * @see hook_drush_init()
 * @see drush_hook_COMMAND_validate()
 * @see drush_hook_pre_COMMAND()
 * @see drush_hook_COMMAND()
 * @see drush_hook_post_COMMAND()
 * @see drush_hook_post_COMMAND_rollback()
 * @see drush_hook_COMMAND_rollback()
 * @see drush_hook_pre_COMMAND_rollback()
 * @see drush_hook_COMMAND_validate_rollback()
 * @see hook_drush_exit()
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Take action before any command is run. Logging an error stops command execution.
 */
function hook_drush_init() {

}

/**
 * Run before a specific command executes.
 *
 * Logging an error stops command execution, and the rollback function (if any)
 * for each hook implementation is invoked.
 *
 * @see drush_hook_COMMAND_validate_rollback()
 */
function drush_hook_COMMAND_validate() {

}

/**
 * Run before a specific command executes. Logging an error stops command execution.
 *
 * Logging an error stops command execution, and the rollback function (if any)
 * for each hook implementation is invoked, in addition to the
 * validate rollback.
 *
 * @see drush_hook_pre_COMMAND_rollback()
 * @see drush_hook_COMMAND_validate_rollback()
 */
function drush_hook_pre_COMMAND() {

}

/**
 * Implementation of the actual drush command.
 *
 * This is where most of the stuff should happen.
 *
 * Logging an error stops command execution, and the rollback function (if any)
 * for each hook implementation is invoked, in addition to pre and
 * validate rollbacks.
 *
 * @see drush_hook_COMMAND_rollback()
 * @see drush_hook_pre_COMMAND_rollback()
 * @see drush_hook_COMMAND_validate_rollback()
 */
function drush_hook_COMMAND() {

}

/**
 * Run after a specific command executes. Logging an error stops command execution.
 *
 * Logging an error stops command execution, and the rollback function (if any)
 * for each hook implementation is invoked, in addition to pre, normal
 * and validate rollbacks.
 *
 * @see drush_hook_post_COMMAND_rollback()
 * @see drush_hook_COMMAND_rollback()
 * @see drush_hook_pre_COMMAND_rollback()
 * @see drush_hook_COMMAND_validate_rollback()
 */
function drush_hook_post_COMMAND() {

}

/**
 * Take action after any command is run.
 */
function hook_drush_exit() {

}

/**
 * Take action after a project has been downloaded.
 */
function hook_drush_pm_post_download($project, $release, $destination) {

}

/**
 * Take action after a project has been updated.
 */
function hook_pm_post_update($release_name, $release_candidate_version, $project_parent_path) {

}

/**
 * Adjust the location that a project should be downloaded to.
 */
function hook_drush_pm_adjust_download_destination(&$project, $release) {
  if ($some_condition) {
    $project['project_install_location'] = '/path/to/install/to/' . basename($project['full_project_path']);
  }
}

/**
 * Post-sync sanitization example.  This is equivalent to
 * the built-in --sanitize option of sql-sync, but simplified
 * to only work with default values on Drupal 6 + mysql.
 *
 * We test for both 'my-sanitize' and 'destination-my-sanitize'
 * options because we want to allow options set in a site-alias
 * to control the post-sync operations.  The options from the
 * destination alias are applied to the drush options context
 * with the prefix 'destination-'.
 *
 * @see drush_sql_pre_sql_sync().
 */
function drush_hook_pre_sql_sync($source = NULL, $destination = NULL) {
  if (drush_get_option(array('my-sanitize', 'destination-my-sanitize'), FALSE)) {
    drush_sql_register_post_sync_op('my-sanitize-id', 
      dt('Reset passwords and email addresses in user table', 
      "update users set pass = MD5('password'), mail = concat('user+', uid, '@localhost') where uid > 0;");
  }
}

/**
 * @} End of "addtogroup hooks".
 */
