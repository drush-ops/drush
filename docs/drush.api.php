<?php

/**
 * @file
 * Documentation of the Drush API.
 */

/**
 * Declare a new command.
 */
function hook_drush_command() {
  // To learn more, run `drush topic docs-commands` and `drush topic docs-examplecommand`
}

/**
 * All drush commands are invoked in a specific order, using
 * drush-made hooks, very similar to the Drupal hook system. See drush_invoke()
 * for the actual implementation.
 *
 * For any commandfile named "hook", the following hooks are called, in
 * order, for the command "COMMAND":
 *
 * 0. drush_COMMAND_init()
 * 1. drush_hook_COMMAND_pre_validate()
 * 2. drush_hook_COMMAND_validate()
 * 3. drush_hook_pre_COMMAND()
 * 4. drush_hook_COMMAND()
 * 5. drush_hook_post_COMMAND()
 *
 * For example, here are the hook opportunities for a mysite.drush.inc file
 * that wants to hook into the `pm-download` command.
 *
 * 1. drush_mysite_pm_download_pre_validate()
 * 2. drush_mysite_pm_download_validate()
 * 3. drush_mysite_pre_pm_download()
 * 4. drush_mysite_pm_download()
 * 5. drush_mysite_post_pm_download()
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
 * 5. drush_mysite_pm_download_pre_validate_rollback()
 *
 * Before any command is called, hook_drush_init() is also called.
 * hook_drush_exit() is called at the very end of command invocation.
 *
 * @see includes/command.inc
 *
 * @see hook_drush_init()
 * @see drush_COMMAND_init()
 * @see drush_hook_COMMAND_pre_validate()
 * @see drush_hook_COMMAND_validate()
 * @see drush_hook_pre_COMMAND()
 * @see drush_hook_COMMAND()
 * @see drush_hook_post_COMMAND()
 * @see drush_hook_post_COMMAND_rollback()
 * @see drush_hook_COMMAND_rollback()
 * @see drush_hook_pre_COMMAND_rollback()
 * @see drush_hook_COMMAND_validate_rollback()
 * @see drush_hook_COMMAND_pre_validate_rollback()
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
 * Run before a specific command validates.
 *
 * Logging an error stops command execution, and the rollback function (if any)
 * for each hook implementation is invoked.
 *
 * @see drush_hook_COMMAND_pre_validate_rollback()
 */
function drush_hook_COMMAND_pre_validate() {

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
 * @return
 *   The return value will be passed along to the caller if --backend option is
 *   present. A boolean FALSE indicates failure and rollback will be intitated.
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
 * A commandfile may choose to decline to load for the current bootstrap
 * level by returning FALSE. This hook must be placed in MODULE.drush.load.inc.
 * @see drush_commandfile_list().
 */
function hook_drush_load() {

}

/**
 * A commandfile may adjust the contents of any command structure
 * prior to dispatch.  @see core_drush_command_alter() for an example.
 */
function hook_drush_command_alter(&$command) {

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
 * Automatically download project dependencies at pm-enable time.
 * Use a pre-pm_enable hook to download before your module is enabled,
 * or a post-pm_enable hook (drush_hook_post_pm_enable) to run after
 * your module is enabled.
 *
 * Your hook will be called every time pm-enable is executed; you should
 * only download dependencies when your module is being enabled.  Respect
 * the --skip flag, and take no action if it is present.
 */
function drush_hook_pre_pm_enable() {
  // Get the list of modules being enabled; only download dependencies if our module name appears in the list
  $modules = drush_get_context('PM_ENABLE_MODULES');
  if (in_array('hook', $modules) && !drush_get_option('skip')) {
    $url = 'http://server.com/path/MyLibraryName.tgz';
    $path = drush_get_context('DRUSH_DRUPAL_ROOT');
    if (module_exists('libraries')) {
      $path .= '/' . libraries_get_path('MyLibraryName') . '/MyLibraryName.tgz';
    }
    else {
      $path .= '/'. drupal_get_path('module', 'hook') . '/MyLibraryName.tgz';
    }
    drush_download_file($url, $path) && drush_tarball_extract($path);
  }
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
 * Add help components to a command
 */
function hook_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sync') {
    $command['options']['myoption'] = "Description of modification of sql-sync done by hook";
    $command['sub-options']['sanitize']['my-sanitize-option'] = "Description of sanitization option added by hook (grouped with --sanitize option)";
  }
  if ($command['command'] == 'global-options') {
    // Recommended: don't show global hook options in brief global options help.
    if ($command['#brief'] === FALSE) {
      $command['options']['myglobaloption'] = 'Description of option used globally in all commands (e.g. in a commandfile init hook)';
    }
  }
}

/**
 * Add/edit options to cache-clear command
 */
function hook_drush_cache_clear(&$types) {
  $types['views'] = 'views_invalidate_cache';
}

/**
 * Inform drush about one or more engine types.
 *
 * This hook allow to declare available engine types, the cli option to select
 * between engine implementatins, which one to use by default, global options
 * and other parameters. Commands may override this info when declaring the
 * engines they use.
 *
 * @return
 *   An array whose keys are engine type names and whose values describe
 *   the characteristics of the engine type in relation to command definitions:
 *
 *   - description: The engine type description.
 *   - topic: If specified, the name of the topic command that will
 *     display the automatically generated topic for this engine.
 *   - topic-file: If specified, the path to the file that will be
 *     displayed at the head of the automatically generated topic for
 *     this engine.  This path is relative to the Drush root directory;
 *     non-core commandfiles should therefore use:
 *       'topic-file' => dirname(__FILE__) . '/mytopic.html';
 *   - topics: If set, contains a list of topics that should be added to
 *     the "Topics" section of any command that uses this engine.  Note
 *     that if 'topic' is set, it will automatically be added to the topics
 *     list, and therefore does not need to also be listed here.
 *   - option: The command line option to choose an implementation for
 *     this engine type.
 *     FALSE means there's no option. That is, the engine type is for internal
 *     usage of the command and thus an implementation is not selectable.
 *   - default: The default implementation to use by the engine type.
 *   - options: Engine options common to all implementations.
 *   - add-options-to-command: If there's a single implementation for this
 *     engine type, add its options as command level options.
 *   - combine-help: If there are multiple implementations for this engine
 *     type, then instead of adding multiple help items in the form of
 *     --engine-option=engine-type [description], instead combine all help
 *     options into a single --engine-option that lists the different possible
 *     values that can be used.
 *
 * @see drush_get_engine_types_info()
 * @see pm_drush_engine_type_info()
 */
function hook_drush_engine_type_info() {
  return array(
    'dessert' => array(
      'description' => 'Choose a dessert while the sandwich is baked.',
      'option' => 'dessert',
      'default' => 'ice-cream',
      'options' => 'sweetness',
      'add-options-to-command' => FALSE,
    ),
  );
}

/**
 * Inform drush about one or more engines implementing a given engine type.
 *
 *   - description: The engine implementation's description.
 *   - engine-class:  The class that contains the engine implementation.
 *       Defaults to the engine type key (e.g. 'ice-cream').
 *   - verbose-only:  The engine implementation will only appear in help
 *       output in --verbose mode.
 *
 * This hook allow to declare implementations for an engine type.
 *
 * @see pm_drush_engine_package_handler()
 * @see pm_drush_engine_version_control()
 */
function hook_drush_engine_ENGINE_TYPE() {
  return array(
    'ice-cream' => array(
      'description' => 'Feature rich ice-cream with all kind of additives.',
      'options' => array(
        'flavour' => 'Choose your favorite flavour',
      ),
    ),
  );
}

/**
 * Alter the order that hooks are invoked.
 *
 * When implementing a given hook we may need to ensure it is invoked before
 * or after another implementation of the same hook. For example, let's say
 * you want to implement a hook that would be called after drush_make. You'd
 * write a drush_MY_MODULE_post_make() function. But if you need your hook to
 * be called before drush_make_post_make(), you can ensure this by implemen-
 * ting MY_MODULE_drush_invoke_alter().
 *
 * @see drush_command_invoke_all_ref()
 */
function hook_drush_invoke_alter($modules, $hook) {
  if ($hook == 'some_hook') {
    // Take the module who's hooks would normally be called last
    $module = array_pop($modules);
    // Ensure it'll be called first for 'some_hook'
    array_unshift($modules, $module);
  }
}


/**
 * @} End of "addtogroup hooks".
 */
