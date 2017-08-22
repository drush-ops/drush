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
    $uid = $log_entry['user']->id();
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

// Get a $_SERVER key, or equivalent environment variable
// if it is not set in $_SERVER.
function runserver_env($key) {
  if (isset($_SERVER[$key])) {
    return $_SERVER[$key];
  }
  else {
    return getenv($key);
  }
}

$url = parse_url($_SERVER["REQUEST_URI"]);
if (file_exists('.' . urldecode($url['path']))) {
  // Serve the requested resource as-is.
  return FALSE;
}

// We set the base_url so that Drupal generates correct URLs for runserver
// (e.g. http://127.0.0.1:8888/...), but can still select and serve a specific
// site in a multisite configuration (e.g. http://mysite.com/...).
$base_url = runserver_env('RUNSERVER_BASE_URL');

// The built in webserver incorrectly sets $_SERVER['SCRIPT_NAME'] when URLs
// contain multiple dots (such as config entity IDs) in the path. Since this is
// a virtual resource, served by index.php set the script name explicitly.
// See https://github.com/drush-ops/drush/issues/2033 for more information.
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Include the main index.php and let Drupal take over.
// n.b. Drush sets the cwd to the Drupal root during bootstrap.
include 'index.php';
