<?php

/**
 * Install Drupal 7+
 */
function drush_core_site_install_version($profile, array $additional_form_options = array()) {
  if (is_null($profile)) {
    $profile = 'standard';
  }

  require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';

  $db_spec = _drush_sql_get_db_spec();

  $account_pass = drush_get_option('account-pass', drush_generate_password());
  $account_name = drush_get_option('account-name', 'admin');
  $settings = array(
    'parameters' => array(
      'profile' => $profile,
      'locale' => drush_get_option('locale', 'en'),
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

  drush_log(dt('Starting Drupal installation. This takes a few seconds ...'), 'ok');
  drush_op('install_drupal', $settings);
  drush_log(dt('Installation complete.  User name: @name  User password: @pass', array('@name' => $account_name, '@pass' => $account_pass)), 'ok');
}
