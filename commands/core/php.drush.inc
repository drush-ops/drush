<?php

/**
 * Implements hook_drush_command().
 */
function php_drush_command() {
  $items = array();
  $items['php'] = array(
    'description' => 'Open an interactive shell on a Drupal site.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'handle-remote-commands' => TRUE,
    'arguments' => array(
      // 'path' => 'Path to open. If omitted, the site front page will be opened.',
    ),
    'options' => array(
      // 'browser' => 'Specify a particular browser (defaults to operating system default). Set to 0 to suppress opening a browser.',
    ),
    'examples' => array(
//      'drush browse' => 'Open default web browser (if configured or detected) to the site front page.',
//      'drush browse node/1' => 'Open web browser to the path node/1.',
//      'drush @example.prod' => 'Open a browser to the web site specified in a site alias.',
//      'drush browse --browser=firefox admin' => 'Open Firefox web browser to the path \'admin\'.',
    ),
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_php() {
  // Redispatch if called against a remote-host so a browser is started on the
  // the *local* machine.
  $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
  if (drush_sitealias_is_remote_site($alias)) {
    // $site_record = drush_sitealias_get_record($alias);
//    $return = drush_invoke_process($site_record, 'site-browse', func_get_args(), drush_redispatch_get_options(), array('integrate' => FALSE));
//    if ($return['error_status']) {
//      return drush_set_error('Unable to execute site browse.');
//    }
//    else {
//      $link = $return['object'];
//    }
  }
  else {
    drush_bootstrap_max();
    // @todo - soon unneeded.
    require_once DRUSH_BASE_PATH . '/vendor/autoload.php';

    $boris = new \Boris\Boris(drush_sitealias_bootstrapped_site_name() . '> ');
    $boris->setInspector(new \Boris\ColoredInspector());
    $boris->start();
  }
}
