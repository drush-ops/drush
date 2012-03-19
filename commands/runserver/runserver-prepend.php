<?php

// We set the base_url so that Drupal generates correct URLs for runserver
// (e.g. http://127.0.0.1:8888/...), but can still select and serve a specific
// site in a multisite configuration (e.g. http://mysite.com/...).
$base_url = runserver_env('RUNSERVER_BASE_URL');

// We hijack filter_init (which core filter module does not implement) as
// a convenient place to affect early changes.
if (!function_exists('filter_init')) {
  // Check function_exists as a safety net in case it is added in future.
  function filter_init() {
    global $conf, $user;
    // Inject values into the $conf array - will apply to all sites.
    // This can be a useful place to apply generic development settings.
    $conf_inject = unserialize(urldecode(runserver_env('RUNSERVER_CONF')));
    // Merge in the injected conf, overriding existing items.
    $conf = array_merge($conf, $conf_inject);
    $uid = runserver_env('RUNSERVER_USER');
    if (isset($_GET['login']) && $user->uid == 0 && !empty($uid)) {
      // If a user was provided, log in as this user.
      $user = user_load($uid);
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
    $message = strtr('Watchdog: !message | severity: !severity | type: !type | uid: !uid | !ip | !request_uri | !referer | !link', array(
      '!message'     => strip_tags(!isset($log_entry['variables']) ? $log_entry['message'] : strtr($log_entry['message'], $log_entry['variables'])),
      '!severity'    => $log_entry['severity'],
      '!type'        => $log_entry['type'],
      '!ip'          => $log_entry['ip'],
      '!request_uri' => $log_entry['request_uri'],
      '!referer'     => $log_entry['referer'],
      '!uid'         => $log_entry['user']->uid,
      '!link'        => strip_tags($log_entry['link']),
    ));
    error_log($message);
  }
}

function runserver_env($key) {
  if (isset($_SERVER[$key])) {
    return $_SERVER[$key];
  }
  else {
    return getenv($key);
  }
}