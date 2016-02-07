<?php

/**
 * Implements hook_drush_command().
 */
function browse_drush_command() {
  $items = array();
  $items['browse'] = array(
    'description' => 'Display a link to a given path or open link in a browser.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'handle-remote-commands' => TRUE,
    'arguments' => array(
      'path' => 'Path to open. If omitted, the site front page will be opened.',
    ),
    'options' => array(
      'browser' => 'Specify a particular browser (defaults to operating system default). Use --no-brower to suppress opening a browser.',
      'redirect-port' => 'The port that the web server is redirected to (e.g. when running within a Vagrant environment)',
    ),
    'examples' => array(
      'drush browse' => 'Open default web browser (if configured or detected) to the site front page.',
      'drush browse node/1' => 'Open web browser to the path node/1.',
      'drush @example.prod' => 'Open a browser to the web site specified in a site alias.',
      'drush browse --browser=firefox admin' => 'Open Firefox web browser to the path \'admin\'.',
    ),
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_browse($path = '') {
  // Redispatch if called against a remote-host so a browser is started on the
  // the *local* machine.
  $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
  if (drush_sitealias_is_remote_site($alias)) {
    $site_record = drush_sitealias_get_record($alias);
    $return = drush_invoke_process($site_record, 'browse', func_get_args(), drush_redispatch_get_options(), array('integrate' => TRUE));
    if ($return['error_status']) {
      return drush_set_error('Unable to execute browse command on remote alias.');
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
    $link = drush_url($path, array('absolute' => TRUE));
  }

  drush_start_browser($link);
  return $link;
}
