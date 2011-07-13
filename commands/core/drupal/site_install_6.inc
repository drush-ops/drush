<?php

/**
 * Install Drupal 6.x
 */
function drush_core_site_install_version($profile) {
  drush_log(dt('Starting Drupal installation. This takes a few seconds ...'), 'ok');
  if (is_null($profile)) {
    $profile = 'default';
  }
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  // We need to disable reporting of E_NOTICE if we want to read the command's output
  // on Windows, because of how Windows is handling output order when using 2>&1
  // redirect added to the command in drush_shell_exec(). We will actually take out
  // all but fatal errors.  See http://drupal.org/node/985716 for more information.
  $phpcode = 'error_reporting(E_ERROR);' . _drush_site_install6_cookies($profile). ' include("'. $drupal_root .'/install.php");';
  drush_shell_exec('php -r %s', $phpcode);
  $cli_output = drush_shell_exec_output();
  $cli_cookie = end($cli_output);

  // We need to bootstrap the database to be able to check the progress of the 
  // install batch process since we're not duplicating the install process using
  // drush_batch functions, but calling the process directly.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);

  $status = _drush_site_install6_stage($profile, $cli_cookie, "start");
  if ($status === FALSE) {
    return FALSE;
  }

  $status = _drush_site_install6_stage($profile, $cli_cookie, "do_nojs");
  if ($status === FALSE) {
    return FALSE;
  }

  $status = _drush_site_install6_stage($profile, $cli_cookie, "finished");
  if ($status === FALSE) {
    return FALSE;
  }

  $account_pass = drush_get_option('account-pass', drush_generate_password());
  $account_name = drush_get_option('account-name', 'admin');
  $phpcode = _drush_site_install6_cookies($profile, $cli_cookie);
  $phpcode .= '
  $_POST = array (
    "site_name" => "'. drush_get_option('site-name', 'Site-Install') .'",
    "site_mail" => "'. drush_get_option('site-mail', 'admin@example.com') .'",
    "account" => array (
      "name" => "'. $account_name .'",
      "mail" => "'. drush_get_option('account-mail', 'admin@example.com') .'",
      "pass" => array (
        "pass1" => "'. $account_pass .'",
        "pass2" => "'. $account_pass .'"
      )
    ),
    "date_default_timezone"=>"0",
    "clean_url"=>'. drush_get_option('clean-url', TRUE) .',
    "form_id"=>"install_configure_form",
    "update_status_module" => array("1"=>"1")
  );
  include("'. $drupal_root .'/install.php");';
  drush_shell_exec('php -r %s', $phpcode);
  drush_log(dt('Installation complete.  User name: @name  User password: @pass', array('@name' => $account_name, '@pass' => $account_pass)), 'ok');
}

/**
 * Submit a given op to install.php; if a meta "Refresh" tag
 * is returned in the result, then submit that op as well.
 */
function _drush_site_install6_stage($profile, $cli_cookie, $initial_op) {
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  // Remember the install task at the start of the stage
  $install_task = _drush_site_install6_install_task();
  $op = $initial_op;
  while (!empty($op)) {
    $phpcode = _drush_site_install6_cookies($profile, $cli_cookie). ' $_GET["op"]="' . $op . '"; include("'. $drupal_root .'/install.php");';
    drush_shell_exec('php -r %s', $phpcode);
    $output = implode("\n", drush_shell_exec_output());
    // Check for a "Refresh" back to the do_nojs op; e.g.:
    //   <meta http-equiv="Refresh" content="0; URL=http://default/install.php?locale=en&profile=wk_profile6&id=1&op=do_nojs">
    // If this pattern is NOT found, then go on to the "finished" step.
    $matches = array();
    $match_result = preg_match('/http-equiv="Refresh".*op=([a-zA-Z0-9_]*)/', $output, $matches);
    if ($match_result) {
      $op = $matches[1];
    }
    else {
      $op = '';
    }
  }
  if (($install_task == _drush_site_install6_install_task()) && ($initial_op != "finished")) {
    return drush_set_error('DRUSH_SITE_INSTALL_FAILED', dt("The site install task '!task' failed.", array('!task' => $install_task)));
  }
  return TRUE;
}

/**
 * Utility function to grab/set current "cli cookie".
 */
function _drush_site_install6_cookies($profile, $cookie = NULL) {
  $drupal_base_url = parse_url(drush_get_option('uri', 'http://default'));
  $output = '$_GET=array("profile"=>"' . $profile . '", "locale"=>"' . drush_get_option('locale', 'en') . '", "id"=>"1"); $_REQUEST=&$_GET;';
  $output .= 'define("DRUSH_SITE_INSTALL6", TRUE);$_SERVER["SERVER_SOFTWARE"] = NULL;';
  $output .= '$_SERVER["SCRIPT_NAME"] = "/install.php";';
  $output .= '$_SERVER["HTTP_HOST"] = "'.$drupal_base_url['host'].'";';
  $output .= '$_SERVER["REMOTE_ADDR"] = "127.0.0.1";';

  if ($cookie) {
    $output .= sprintf('$_COOKIE=unserialize("%s");', str_replace('"', '\"', $cookie));
  }
  else {
    $output .= 'function _cli_cookie_print(){print(serialize(array(session_name()=>session_id())));}
register_shutdown_function("_cli_cookie_print");';
  }

  return $output;
}

/**
 * Utility function to check the install_task.  We are
 * not bootstrapped to a high enough level to use variable_get.
 */
function _drush_site_install6_install_task() {
  if ($data = db_result(db_query("SELECT value FROM {variable} WHERE name = 'install_task'",1))) {
    $result = unserialize($data);
  }
  return $result;
}
