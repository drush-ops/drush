<?php

use Drush\Log\LogLevel;

/**
 * Install Drupal 8+
 */
function drush_core_site_install_version($profile, array $additional_form_options = array()) {
  require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';
  $class_loader = drush_drupal_load_autoloader(DRUPAL_ROOT);

  if (!isset($profile)) {
    // If there is an installation profile that acts as a distribution, use that
    // one.
    $install_state = array('interactive' => FALSE) + install_state_defaults();
    try {
      install_begin_request($class_loader, $install_state);
      $profile = _install_select_profile($install_state);
    }
    catch (\Exception $e) {
      // This is only a best effort to provide a better default, no harm done
      // if it fails.
    }
    if (empty($profile)) {
      $profile = 'standard';
    }
  }

  $sql = drush_sql_get_class();
  $db_spec = $sql->db_spec();

  $account_name = drush_get_option('account-name', 'admin');
  $account_pass = drush_get_option('account-pass', FALSE);
  $show_password = drush_get_option('show-passwords', !$account_pass);
  if (!$account_pass) {
    $account_pass = drush_generate_password();
  }
  $settings = array(
    'parameters' => array(
      'profile' => $profile,
      'langcode' => drush_get_option('locale', 'en'),
    ),
    'forms' => array(
      'install_settings_form' => array(
        'driver' => $db_spec['driver'],
        $db_spec['driver'] => $db_spec,
        'op' => dt('Save and continue'),
      ),
      'install_configure_form' => array(
        'site_name' => drush_get_option('site-name', 'Site-Install'),
        'site_mail' => drush_get_option('site-mail', 'admin@example.com'),
        'account' => array(
          'name' => $account_name,
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
        'op' => dt('Save and continue'),
      ),
    ),
  );

  // Merge in the additional options.
  foreach ($additional_form_options as $key => $value) {
    $current = &$settings['forms'];
    foreach (explode('.', $key) as $param) {
      $current = &$current[$param];
    }
    $current = $value;
  }

  $msg = 'Starting Drupal installation. This takes a while.';
  if (is_null(drush_get_option('notify'))) {
    $msg .= ' Consider using the --notify global option.';
  }
  drush_log(dt($msg), LogLevel::OK);
  drush_op('install_drupal', $class_loader, $settings);
  if ($show_password) {
    drush_log(dt('Installation complete.  User name: @name  User password: @pass', array('@name' => $account_name, '@pass' => $account_pass)), LogLevel::OK);
  }
  else {
    drush_log(dt('Installation complete.'), LogLevel::OK);
  }

}
