<?php

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
  }
}

// We hijack system_watchdog (which core system module does not implement) as
// a convenient place to capture logs.
if (!function_exists('system_watchdog')) {
  // Check function_exists as a safety net in case it is added in future.
  function system_watchdog($log_entry = array()) {
    // Drupal <= 7.x defines VERSION. Drupal 8 defines Drupal::VERSION instead.
    if (defined('VERSION')) {
      $uid = $log_entry['user']->uid;
    }
    else {
      $uid = $log_entry['user']->id();
    }
    $message = strtr('Watchdog: !message | severity: !severity | type: !type | uid: !uid | !ip | !request_uri | !referer | !link', array(
      '!message'     => strip_tags(!isset($log_entry['variables']) ? $log_entry['message'] : strtr($log_entry['message'], $log_entry['variables'])),
      '!severity'    => $log_entry['severity'],
      '!type'        => $log_entry['type'],
      '!ip'          => $log_entry['ip'],
      '!request_uri' => $log_entry['request_uri'],
      '!referer'     => $log_entry['referer'],
      '!uid'         => $uid,
      '!link'        => strip_tags($log_entry['link']),
    ));
    error_log($message);
  }
}

// Pass through to the default runserver router.
include __DIR__ . '/rs-router.php';
