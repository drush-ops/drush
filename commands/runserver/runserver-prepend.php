<?php
/**
 * @file
 * This file is included using the php "auto_prepend_file" option whenever
 * Drupal is used with runserver.
 * We use this to inject some specific changes so Drupal works correctly in
 * this environment.
 */

// We set the base_url so that Drupal generates correct URLs for runserver
// (e.g. http://localhost:8888/...), but can still select and serve a specific
// site in a multisite configuration (e.g. http://mysite.com/...).
$base_url = $_SERVER['RUNSERVER_BASE_URL'];

/**
 * Configuration added here will apply to all sites.
 * This can be a useful place to apply generic development settings.
 * We hijack system_boot (which core system module does not implement) as
 * a convenient place to inject values into the $conf array.
 */
if (!function_exists('system_boot')) {
  // Check function_exists as a safety net in case it is added in future.
  function system_boot() {
    global $conf;
    $conf_inject = unserialize(urldecode($_SERVER['RUNSERVER_CONF']));
    // Merge in the injected conf, overriding existing items.
    $conf = array_merge($conf, $conf_inject);
  }
}