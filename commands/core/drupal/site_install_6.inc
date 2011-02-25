<?php

/**
 * Install Drupal 6.x
 */
function drush_core_site_install_version($profile) {
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
  $phpcode = _drush_site_install6_cookies($profile, $cli_cookie). ' $_GET["op"]="start"; include("'. $drupal_root .'/install.php");';
  drush_shell_exec('php -r %s', $phpcode);

  $phpcode = _drush_site_install6_cookies($profile, $cli_cookie). ' $_GET["op"]="do_nojs"; include("'. $drupal_root .'/install.php");';
  drush_shell_exec('php -r %s', $phpcode);

  $phpcode = _drush_site_install6_cookies($profile, $cli_cookie). ' $_GET["op"]="finished"; include("'. $drupal_root .'/install.php");';
  drush_shell_exec('php -r %s', $phpcode);

  $account_pass = drush_get_option('account-pass', 'admin');
  $phpcode = _drush_site_install6_cookies($profile, $cli_cookie);
  $phpcode .= '
  $_POST = array (
    "site_name" => "'. drush_get_option('site-name', 'Site-Install') .'", 
    "site_mail" => "'. drush_get_option('site-mail', 'admin@example.com') .'", 
    "account" => array (
      "name" => "'. drush_get_option('account-name', 'admin') .'",
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
}

/**
 * Utility function to grab/set current "cli cookie".
 * 
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
