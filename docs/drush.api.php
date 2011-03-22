<?php

/**
 * @file
 * Documentation of the Drush API.
 *
 * All drush commands are invoked in a specific order, using
 * drush-made hooks, very similar to the Drupal hook system. See drush_invoke()
 * for the actual implementation.
 *
 * For any commandfile named "hook", the following hooks are called, in
 * order, for the command "COMMAND":
 *
 * 0. drush_COMMAND_init()
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
 * Note that the drush_COMMAND_init() hook is only for use by the
 * commandfile that defines the command.
 *
 * If any of hook function fails, either by calling drush_set_error
 * or by returning FALSE as its function result, then the rollback
 * mechanism is called.  To fail with an error, call drush_set_error:
 *
 *   return drush_set_error('MY_ERROR_CODE', dt('Error message.'));
 *
 * To allow the user to confirm or cancel a command, use drush_confirm
 * and drush_user_abort:
 *
 *   if (!drush_confirm(dt('Are you sure?'))) {
 *     return drush_user_abort();
 *   }
 *
 * The rollback mechanism will call, in reverse, all _rollback hooks.
 * The mysite command file can implement the following rollback hooks:
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
 * @see drush_COMMAND_init()
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
 * Initialize a command prior to validation.  If a command
 * needs to bootstrap to a higher level, this is best done in
 * the command init hook.  It is permisible to bootstrap in
 * any hook, but note that if bootstrapping adds more commandfiles
 * (*.drush.inc) to the commandfile list, the newly-added
 * commandfiles will not have any hooks called until the next
 * phase.  For example, a command that calls drush_bootstrap_max()
 * in drush_hook_COMMAND() would only permit commandfiles from
 * modules enabled in the site to participate in drush_hook_post_COMMAND()
 * hooks.
 */
function drush_COMMAND_init() {
  drush_bootstrap_max();
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

/*
 * A commandfile may choose to decline to load for the current bootstrap
 * level by returning FALSE. This hook must be placed in MODULE.drush.load.inc.
 * @see drush_commandfile_list().
 */
function hook_drush_load() {

}

/**
 * Take action after a project has been downloaded.
 */
function hook_drush_pm_post_download($project, $release) {

}

/**
 * Take action after a project has been updated.
 */
function hook_pm_post_update($release_name, $release_candidate_version, $project_parent_path) {

}

/**
 * Adjust the location that a project should be copied to after being downloaded.
 *
 * See @pm_drush_pm_download_destination_alter().
 */
function hook_drush_pm_download_destination_alter(&$project, $release) {
  if ($some_condition) {
    $project['project_install_location'] = '/path/to/install/to/' . $project['project_dir'];
  }
}

/**
 * Add information to the upgrade project map; this information
 * will be shown to the user when upgrading Drupal to the next
 * major version if the module containing this hook is enabled.
 *
 * @see drush_upgrade_project_map().
 */
function hook_drush_upgrade_project_map_alter(&$project_map) {
  $project_map['warning']['hook'] = dt("You need to take special action before upgrading this module. See http://mysite.com/mypage for more information.");
}

/**
 * Sql-sync sanitization example.  This is equivalent to
 * the built-in --sanitize option of sql-sync, but simplified
 * to only work with default values on Drupal 6 + mysql.
 *
 * @see sql_drush_sql_sync_sanitize().
 */
function hook_drush_sql_sync_sanitize($source) {
  drush_sql_register_post_sync_op('my-sanitize-id',
    dt('Reset passwords and email addresses in user table'),
    "update users set pass = MD5('password'), mail = concat('user+', uid, '@localhost') where uid > 0;");
}

/**
 * Take action before modules are disabled in a major upgrade.
 * Note that when this hook fires, it will be operating on a
 * copy of the database.
 */
function drush_hook_pre_site_upgrade_prepare() {
  // site upgrade prepare will disable contrib_extensions and
  // uninstall the uninstall_extension
  $contrib_extensions = func_get_args();
  $uninstall_extensions = explode(',', drush_get_option('uninstall', ''));
}


/**
 * Add help components to a command
 */
function hook_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sync') {
    $command['options']['myoption'] = "Description of modification of sql-sync done by hook";
    $command['sub-options']['sanitize']['my-sanitize-option'] = "Description of sanitization option added by hook (grouped with --sanitize option)";
  }
}

/**
 * Add/edit options to cache-clear command
 */
function hook_drush_cache_clear(&$types) {
  $types['views'] = 'views_invalidate_cache';
}

/*
 * Make shell aliases and other .bashrc code available during core-cli command.
 *
 * @return
 *   Bash code typically found in a .bashrc file.
 *
 * @see core_cli_bashrc() for an example implementation.
 */
function hook_cli_bashrc() {
  $string = "
    alias siwef='drush site-install wef --account-name=super --account-mail=me@wef'
    alias dump='drush sql-dump --structure-tables-key=wef --ordered-dump'
  ";
  return $string;
}

/**
 * @} End of "addtogroup hooks".
 */
