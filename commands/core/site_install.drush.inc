<?php
// $Id$

// Perform setup tasks for installation.
function drush_core_pre_site_install() {

  if (!$db_spec = drush_core_site_install_db_spec()) {
    drush_set_error(dt('Could not determine database connection parameters. Pass --db-url option.'));
    return;
  }

  
  if ($sites_subdir = drush_get_option('sites-subdir')) {
    // Needed so that we later bootstrap into the right site.
    drush_set_option('uri', $sites_subdir);
  }
 else {
    $sites_subdir = 'default';
  }

  $conf_path = "sites/$sites_subdir";
  $files = "$conf_path/files";
  $settingsfile = "$conf_path/settings.php";
  if (!file_exists($files)) {
    $msg[] = dt('create a @files directory', array('@files' => $files));
  }
  if (!file_exists($settingsfile)) {
    $msg[] = dt('create a @settingsfile file', array('@settingsfile' => $settingsfile));
  }
  $msg[] = dt("DROP your '@db' database and then CREATE a new one.", array('@db' => $db_spec['database']));

  if (!drush_confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
    return drush_set_error('CORE_SITE_INSTALL_ABORT', 'Aborting.');
  }

  // Can't install without sites directory and settings.php.
  if (!file_exists($conf_path)) {
    if (!drush_op('mkdir', $conf_path) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @conf_path', array('@conf_path' => $conf_path)));
      return;
    }
  }
  else {
    drush_log(dt('Sites directory @subdir already exists - proceeding.', array('@subdir' => $conf_path)));
  }
  if (!file_exists($settingsfile)) {
    if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to copy sites/default/default.settings.php to  @settingsfile', array('@settingsfile' => $settingsfile)));
      return;
    }
  }

  // Add a files dir if needed
  if (!file_exists($files)) {
    if (!drush_op('mkdir', $files) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @name', array('@name' => $files)));
      return;
    }
  }

  // Now we can bootstrap up to the specified site if not already there.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);

  // Drop and create DB if needed.
  // @TODO: support db-su like sql sync.

  // Can't use drush_sql_query() since might not have a DB.
  // Get credentials to connect to the server, but not the database which we
  // are about to DROP.

  // Save the database name before we unset() it.
  $db_name = $db_spec['database'];
  $scheme = _drush_sql_get_scheme($db_spec);

  // _drush_sql_get_credentials() will set a default database according to
  // the scheme if one is not set.
  unset($db_spec['database']);
  $credentials = _drush_sql_get_credentials($db_spec);

  $scheme_command = $scheme;
  switch ($scheme) {
    case 'mysql':
      $command_parameter_name = 'execute';
      break;
    case 'pgsql':
      $scheme_command = 'psql';
      $command_parameter_name = 'command';
      break;
  }
  $execute = "$scheme_command $credentials --$command_parameter_name";

  $simulate = drush_get_context('DRUSH_SIMULATE');

  if (drush_op('system', "$execute='DROP DATABASE IF EXISTS `$db_name`'") && !$simulate) {
    drush_set_error(dt('Could not drop database: @name', array('@name' => $db_name)));
    return;
  }

  if (drush_op('system', "$execute='CREATE DATABASE `$db_name`'") && !$simulate) {
    drush_set_error(dt('Could not create new database: @name', array('@name' => $db_name)));
    return;
  }
}

function drush_core_site_install($profile = 'standard') {
  define('MAINTENANCE_MODE', 'install');
  require_once DRUPAL_ROOT . '/includes/install.core.inc';

  $db_spec = drush_core_site_install_db_spec();

  $account_pass = drush_get_option('account-pass', 'admin');
  $settings = array(
    'parameters' => array(
      'profile' => $profile,
      'locale' => drush_get_option('locale', 'en'),
    ),
    'forms' => array(
      'install_settings_form' => $db_spec,
      'install_configure_form' => array(
        'site_name' => drush_get_option('site-name', 'Site-Install'),
        'site_mail' => drush_get_option('site-mail', 'admin@example.com'),
        'account' => array(
          'name' => drush_get_option('account-name', 'admin'),
          'mail' => drush_get_option('account-mail', 'admin@example.com'),
          'pass' => array(
            'pass1' => $account_pass,
            'pass2' => $account_pass,
          ),
        ),
        'update_status_module' => array(
          1 => TRUE,
          2 => TRUE,
        ),
        'clean_url' => drush_get_option('clean-url', TRUE),
      ),
    ),
  );
  drush_log(dt('Starting Drupal installation. This takes 30 seconds or so ...'), 'ok');
  install_drupal($settings);
}

// Return a db_spec based on supplied db_url/db_prefix options or
// an existing settings.php.
function drush_core_site_install_db_spec() {
  if ($db_url = drush_get_option('db-url')) {
    // We were passed a db_url. Usually a fresh site.
    $db_spec = drush_convert_db_from_db_url($db_url);
    $db_spec['db_prefix'] = drush_get_option('db-prefix');
    return $db_spec;
  }
  elseif (drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
    // We have an existing settings.php.
    $db_spec = _drush_sql_get_db_spec();
    $db_spec['db_prefix'] = $GLOBALS['db_prefix'];
    return $db_spec;
  }
  else {
    return FALSE;
  }
}
