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
 * that wants to hook into the `dl` command.
 *
 * 1. drush_mysite_dl_validate()
 * 2. drush_mysite_pre_dl()
 * 3. drush_mysite_dl()
 * 4. drush_mysite_post_dl()
 *
 * If any of those fails, the rollback mechanism is called. It will
 * call, in reverse, all _rollback hooks. The mysite command file can implement
 * the following rollback hooks:
 *
 * 1. drush_mysite_post_dl_rollback()
 * 2. drush_mysite_dl_rollback()
 * 3. drush_mysite_pre_dl_rollback()
 * 4. drush_mysite_dl_validate_rollback()
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
function hook_drush_pm_post_install($project, $release, $destination) {

}

/**
 * Take action after a project has been updated.
 */
function hook_pm_post_update($release_name, $release_candidate_version, $project_parent_path) {

}

/**
 * @} End of "addtogroup hooks".
 */
