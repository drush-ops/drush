<?php

/**
 * @file
 * Browse Drush commands.
 */

/**
 * Implements hook_drush_command().
 */
function browse_drush_command() {
  $items = array();

  $items['browse'] = array(
    'description' => 'Display a link to a given path on the site and open it in a browser.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'handle-remote-commands' => TRUE,
    'arguments' => array(
      'path' => 'Optional path to open. If omitted the site front page will be opened.',
    ),
    'options' => array(
      'browser' => 'Optional value denotes which browser to use (defaults to operating system default). Set to 0 to suppress opening a browser.',
    ),
    'examples' => array(
      'drush browse' => 'Display and open default web browser (if configured or detected) to the site front page.',
      'drush browse node/1' => 'Display and open web browser to the path node/1.',
      'drush @example.prod' => 'Open a remote site in a browser on the local machine.',
      'drush browse --browser=firefox admin' => 'Open firefox web browser to the path admin.',
    ),
  );

  return $items;
}

/**
 * Implements drush_hook_COMMAND().
 *
 * @see browse_drush_command()
 */
function drush_browse($path = '') {
  // Redispatch if called against a remote-host so a browser is started on the
  // the *local* machine.
  $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
  if (drush_sitealias_is_remote_site($alias)) {
    $return = drush_invoke_process($site_record, 'site-browse', func_get_args(), drush_redispatch_get_options(), array(
      'integrate' => FALSE,
    ));
    if ($return['error_status']) {
      return drush_set_error('Unable to execute site browse.');
    }
    else {
      $link = $return['object'];
    }
  }
  else {
    if (!drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      // Fail gracefully if unable to bootstrap Drupal. drush_bootstrap() has
      // already logged an error.
      return FALSE;
    }

    $link = url($path, array('absolute' => TRUE));

    drush_start_browser($link);
    return $link;
  }
}
