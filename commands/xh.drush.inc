<?php

/**
 * @file
 */

/**
 * Implements hook_drush_help_alter().
 */
function xh_drush_help_alter(&$command) {
  if ($command['command'] == 'global-options') {
    // Do not include these in options in standard help.
    if ($command['#brief'] === FALSE) {
      $command['options']['xh'] = array(
        'description' => 'Enable profiling via XHProf',
      );
      $command['sub-options']['xh']['xh-link'] = 'URL to your XHProf report site.';
      $command['sub-options']['xh']['xh-path'] = 'Absolute path to the xhprof project.';
    }
  }
}

function xh_enabled() {
  if (drush_get_option('xh')) {
    if (!extension_loaded('xhprof')) {
      return drush_set_error('You must enable the xhprof PHP extension in your CLI PHP in order to profile.');
    }
    if (!drush_get_option('xh-path', '')) {
      return drush_set_error('You must provide the xh-path option in your drushrc or on the CLI in order to profile.');
    }
    return TRUE;
  }
}

/**
 * Implements hook_drush_init().
 */
function xh_drush_init() {
  if (xh_enabled()) {
    if ($path = drush_get_option('xh-path', '')) {
      include_once $path . '/xhprof_lib/utils/xhprof_lib.php';
      include_once $path . '/xhprof_lib/utils/xhprof_runs.php';
      xhprof_enable(); // @todo support custom flags
    }
  }
}

/**
 * Implements hook_drush_exit().
 */
function xh_drush_exit() {
  if (xh_enabled()) {
    $args = func_get_args();
    $namespace = 'Drush'; // variable_get('site_name', '');
    $xhprof_data = xhprof_disable();
    $xhprof_runs = new XHProfRuns_Default();
    $run_id =  $xhprof_runs->save_run($xhprof_data, $namespace);
    if ($url = xh_link($run_id)) {
      drush_log(dt('XHProf run saved. View report at !url', array('!url' => $url)), 'ok');
    }
  }
}

/**
 * Returns the XHProf link.
 */
function xh_link($run_id) {
  if ($xhprof_url = trim(drush_get_option('xh-link'))) {
    $namespace = 'Drush'; //variable_get('site_name', '');
    return $xhprof_url . '/index.php?run=' . urlencode($run_id) . '&source=' . urlencode($namespace);
  }
  else {
    drush_log('Configure xh_link in order to see a link to the XHProf report for this request instead of this message.');
  }
}
