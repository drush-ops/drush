<?php

/**
 * Implements hook_drush_command().
 */
function cli_drush_command() {
  $items['core-cli'] = array(
    'description' => 'Open an interactive shell on a Drupal site.',
    'aliases' => array('cli', 'php'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'handle-remote-commands' => TRUE,
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_cli_core_cli() {
  $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
  if (drush_sitealias_is_remote_site($alias)) {
    $site_record = drush_sitealias_get_record($alias);

    // @todo: attempt #1: this mostly works, but is nasty. Also, exiting the shell is ugly.
    $cmd = drush_shell_proc_build($site_record, 'drush php', TRUE, TRUE);
    $status = drush_shell_proc_open($cmd);
    if ($status != 0) {
      return drush_set_error('DRUSH_CORE_CLI_ERROR', dt('An error @code occurred while running the command `@command`', array('@command' => $cmd, '@code' => $status)));
    }

    // @todo: attempt #2: Would be nice if we could use our usual drush_invoke_process() instead.
    // $backend_options = array('interactive' => TRUE);
    // $return = drush_invoke_process($site_record, 'core-cli', func_get_args(), drush_redispatch_get_options(), $backend_options);
    // if ($return['error_status']) {
    //   return return drush_set_error('DRUSH_CORE_CLI_ERROR', dt('An error @code occurred while running the command `@command`', array('@command' => $cmd, '@code' => $status)));
    // }
  }
  else {
    drush_bootstrap_max();
    // @todo - Remove after https://github.com/drush-ops/drush/pull/127.
    require_once DRUSH_BASE_PATH . '/../../autoload.php';

    $boris = new \Boris\Boris(drush_sitealias_bootstrapped_site_name() . '> ');

    $boris->setInspector(new \Boris\ColoredInspector());

    // Boris will never return control to us, but our shutdown
    // handler will still run after the user presses ^D.  Mark
    // this command as completed to avoid a spurrious error message.
    drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
    $boris->start();
  }
}
