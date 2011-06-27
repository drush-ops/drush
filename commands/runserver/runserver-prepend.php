<?php

// We set the base_url so that Drupal generates correct URLs for runserver
// (e.g. http://127.0.0.1:8888/...), but can still select and serve a specific
// site in a multisite configuration (e.g. http://mysite.com/...).
$base_url = $_SERVER['RUNSERVER_BASE_URL'];

// We hijack system_boot (which core system module does not implement) as
// a convenient place to affect mid-bootstrap changes.
if (!function_exists('system_boot')) {
  // Check function_exists as a safety net in case it is added in future.
  function system_boot() {
    global $conf, $user;
    // Inject values into the $conf array - will apply to all sites.
    // This can be a useful place to apply generic development settings.
    $conf_inject = unserialize(urldecode($_SERVER['RUNSERVER_CONF']));
    // Merge in the injected conf, overriding existing items.
    $conf = array_merge($conf, $conf_inject);
    if (isset($_SERVER['RUNSERVER_USER'])) {
      // If a user was provided, log in as this user.
      $user = unserialize(urldecode($_SERVER['RUNSERVER_USER']));
      if (function_exists('drupal_session_regenerate')) {
        // Drupal 7
        drupal_session_regenerate();
      }
      else {
        // Drupal 6
        sess_regenerate();
      }
    }
  }
}

// We hijack system_watchdog (which core system module does not implement) as
// a convenient place to capture logs.
if (!function_exists('system_watchdog')) {
  // Check function_exists as a safety net in case it is added in future.
  function system_watchdog($log_entry = array()) {
    static $logs = array();
    $logs[] = $log_entry;
    $data = urlencode(serialize($logs));
    if (function_exists('drupal_add_http_header')) {
      // Drupal 7
      drupal_add_http_header('X-Runserver-Watchdog', $data);
    }
    else {
      // Drupal 6
      drupal_set_header('X-Runserver-Watchdog: ' . $data);
    }
  }
}
