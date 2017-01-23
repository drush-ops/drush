<?php

use Drush\Log\LogLevel;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implementation of hook_drush_help().
 */
function cache_drush_help($section) {
  switch ($section) {
    case 'meta:cache:title':
      return dt('Cache commands');
    case 'meta:cache:summary':
      return dt('Interact with Drupal\'s cache API.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function cache_drush_command() {
  $items = array();

  // We specify command callbacks here because the defaults would collide with
  // the drush cache api functions.
  $items['cache-get'] = array(
    'description' => 'Fetch a cached object and display it.',
    'examples' => array(
      'drush cache-get schema' => 'Display the data for the cache id "schema" from the "cache" bin.',
      'drush cache-get update_available_releases update' => 'Display the data for the cache id "update_available_releases" from the "update" bin.',
    ),
    'arguments' => array(
      'cid' => 'The id of the object to fetch.',
      'bin' => 'Optional. The cache bin to fetch from.',
    ),
    'required-arguments' => 1,
    'callback' => 'drush_cache_command_get',
    'outputformat' => array(
      'default' => 'print-r',
      'pipe-format' => 'var_export',
      'output-data-type' => TRUE,
    ),
    'aliases' => array('cg'),
  );
  $items['cache-clear'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'description' => 'Clear a specific cache, or all drupal caches.',
    'arguments' => array(
      'type' => 'The particular cache to clear. Omit this argument to choose from available caches.',
    ),
    'callback' => 'drush_cache_command_clear',
    'aliases' => array('cc'),
  );
  $items['cache-set'] = array(
    'description' => 'Cache an object expressed in JSON or var_export() format.',
    'arguments' => array(
      'cid' => 'The id of the object to set.',
      'data' => 'The object to set in the cache. Use \'-\' to read the object from STDIN.',
      'bin' => 'Optional. The cache bin to store the object in.',
      'expire' => 'Optional. CACHE_PERMANENT, CACHE_TEMPORARY, or a Unix timestamp.',
      'tags' => 'An array of cache tags.',
    ),
    'required-arguments' => 2,
    'options' => array(
      // Note that this is not an outputformat option.
      'format' => 'Format to parse the object. Use "string" for string (default), and "json" for JSON.',
      'cache-get' => 'If the object is the result a previous fetch from the cache, only store the value in the "data" property of the object in the cache.',
    ),
    'callback' => 'drush_cache_command_set',
    'aliases' => array('cs'),
  );
  $items['cache-rebuild'] = array(
    'description' => 'Rebuild a Drupal 8 site and clear all its caches.',
    'options' => array(),
    'arguments' => array(),
    // Bootstrap to DRUSH_BOOTSTAP_DRUPAL_SITE to pick the correct site.
    // Further bootstrap is done by the rebuild script.
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'core' => array('8+'),
    'aliases' => array('cr', 'rebuild'),
  );

  return $items;
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Array of clear types.
 */
function cache_cache_clear_complete() {
  // Bootstrap as far as possible so that Views and others can list their caches.
  drush_bootstrap_max();
  return array('values' => array_keys(drush_cache_clear_types(TRUE)));
}

function drush_cache_clear_pre_validate($type = NULL) {
  $types = drush_cache_clear_types(drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));
  // Check if the provided type ($type) is a valid cache type.
  if ($type && !array_key_exists($type, $types)) {
    if ($type === 'all' && drush_drupal_major_version() >= 8) {
      return drush_set_error(dt('`cache-clear all` is deprecated for Drupal 8 and later. Please use the `cache-rebuild` command instead.'));
    }
    // If we haven't done a full bootstrap, provide a more
    // specific message with instructions to the user on
    // bootstrapping a Drupal site for more options.
    if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $all_types = drush_cache_clear_types(TRUE);
      if (array_key_exists($type, $all_types)) {
        return drush_set_error(dt("'!type' cache requires a working Drupal site to operate on. Use the --root and --uri options, or a site @alias, or cd to a directory containing a Drupal settings.php file.", array('!type' => $type)));
      }
      else {
        return drush_set_error(dt("'!type' cache is not a valid cache type. There may be more cache types available if you select a working Drupal site.", array('!type' => $type)));
      }
    }
    return drush_set_error(dt("'!type' cache is not a valid cache type.", array('!type' => $type)));
  }
}

/**
 * Command callback for drush cache-clear.
 */
function drush_cache_command_clear($type = NULL) {
  if (!drush_get_option('cache-clear', TRUE)) {
    drush_log(dt("Skipping cache-clear operation due to --cache-clear=0 option."), LogLevel::OK);
    return TRUE;
  }
  $types = drush_cache_clear_types(drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));

  if (!isset($type)) {
    // Don't offer 'all' unless Drush has bootstrapped the Drupal site
    if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      unset($types['all']);
    }
    $type = drush_choice($types, 'Enter a number to choose which cache to clear.', '!key');
    if (empty($type)) {
      return drush_user_abort();
    }
  }
  // Do it.
  drush_op($types[$type]);
  if ($type == 'all' && !drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    drush_log(dt("No Drupal site found, only 'drush' cache was cleared."), LogLevel::WARNING);
  }
  else {
    drush_log(dt("'!name' cache was cleared.", array('!name' => $type)), LogLevel::SUCCESS);
  }
}

/**
 * Print an object returned from the cache.
 *
 * @param $cid
 *   The cache ID of the object to fetch.
 * @param $bin
 *   A specific bin to fetch from. If not specified, the default bin is used.
 */
function drush_cache_command_get($cid = NULL, $bin = NULL) {
  drush_include_engine('drupal', 'cache');
  $result = drush_op('_drush_cache_command_get', $cid, $bin);

  if (empty($result)) {
    return drush_set_error('DRUSH_CACHE_OBJECT_NOT_FOUND', dt('The !cid object in the !bin bin was not found.', array('!cid' => $cid, '!bin' => $bin ? $bin : _drush_cache_bin_default())));
  }
  return $result;
}

/**
 * Set an object in the cache.
 *
 * @param $cid
 *   The cache ID of the object to fetch.
 * @param $data
 *   The data to save to the cache, or '-' to read from STDIN.
 * @param $bin
 *   A specific bin to fetch from. If not specified, the default bin is used.
 * @param $expire
 *   The expiry timestamp for the cached object.
 * @param $tags
 *   Cache tags for the cached object.
 */
function drush_cache_command_set($cid = NULL, $data = '', $bin = NULL, $expire = NULL, $tags = array()) {
  // In addition to prepare, this also validates. Can't easily be in own validate callback as
  // reading once from STDIN empties it.
  $data = drush_cache_set_prepare_data($data);
  if ($data === FALSE && drush_get_error()) {
    // An error was logged above.
    return;
  }

  drush_include_engine('drupal', 'cache');
  return drush_op('_drush_cache_command_set', $cid, $data, $bin, $expire, $tags);
}

function drush_cache_set_prepare_data($data) {
  if ($data == '-') {
    $data = file_get_contents("php://stdin");
  }

  // Now, we parse the object.
  switch (drush_get_option('format', 'string')) {
    case 'json':
      $data = drush_json_decode($data);
      break;
  }

  if (drush_get_option('cache-get')) {
    // $data might be an object.
    if (is_object($data) && $data->data) {
      $data = $data->data;
    }
    // But $data returned from `drush cache-get --format=json` will be an array.
    elseif (is_array($data) && isset($data['data'])) {
      $data = $data['data'];
    }
    else {
      // If $data is neither object nor array and cache-get was specified, then
      // there is a problem.
      return drush_set_error('CACHE_INVALID_FORMAT', dt("'cache-get' was specified as an option, but the data is neither an object or an array."));
    }
  }

  return $data;
}

/**
 * All types of caches available for clearing. Contrib commands can alter in their own.
 */
function drush_cache_clear_types($include_bootstrapped_types = FALSE) {
  drush_include_engine('drupal', 'cache');
  $types = _drush_cache_clear_types($include_bootstrapped_types);

  // Include the appropriate environment engine, so callbacks can use core
  // version specific cache clearing functions directly.
  drush_include_engine('drupal', 'environment');

  // Command files may customize $types as desired.
  drush_command_invoke_all_ref('drush_cache_clear', $types, $include_bootstrapped_types);

  return $types;
}

/**
 * Clear caches internal to drush core.
 */
function drush_cache_clear_drush() {
  drush_cache_clear_all(NULL, 'default'); // commandfiles, etc.
  drush_cache_clear_all(NULL, 'complete'); // completion
  // Release XML. We don't clear tarballs since those never change.
  $matches = drush_scan_directory(drush_directory_cache('download'), "/^https---updates.drupal.org-release-history/", array('.', '..'));
  array_map('unlink', array_keys($matches));
}

/**
 * Rebuild a Drupal 8 site.
 *
 * This is a transpose of core/rebuild.php. Additionally
 * it also clears drush cache and drupal render cache.
 */
function drush_cache_rebuild() {
  if (!drush_get_option('cache-clear', TRUE)) {
    drush_log(dt("Skipping cache-clear operation due to --cache-clear=0 option."), LogLevel::OK);
    return TRUE;
  }
  chdir(DRUPAL_ROOT);

  // Clear the APC cache to ensure APC class loader is reset.
  if (function_exists('apc_fetch')) {
    apc_clear_cache('user');
  }
  // Clear user cache for all major platforms.
  $user_caches = [
    'apcu_clear_cache',
    'wincache_ucache_clear',
    'xcache_clear_cache',
  ];
  array_map('call_user_func', array_filter($user_caches, 'is_callable'));

  $autoloader = drush_drupal_load_autoloader(DRUPAL_ROOT);
  require_once DRUSH_DRUPAL_CORE . '/includes/utility.inc';

  $request = Request::createFromGlobals();
  // Ensure that the HTTP method is set, which does not happen with Request::createFromGlobals().
  $request->setMethod('GET');
  // Manually resemble early bootstrap of DrupalKernel::boot().
  require_once DRUSH_DRUPAL_CORE . '/includes/bootstrap.inc';
  DrupalKernel::bootEnvironment();
  // Avoid 'Only variables should be passed by reference'
  $root  = DRUPAL_ROOT;
  $site_path = DrupalKernel::findSitePath($request);
  Settings::initialize($root, $site_path, $autoloader);

  // Use our error handler since _drupal_log_error() depends on an unavailable theme system (ugh).
  set_error_handler('drush_error_handler');

  // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
  drupal_rebuild($autoloader, $request);
  drush_log(dt('Cache rebuild complete.'), LogLevel::OK);

  // As this command replaces `drush cache-clear all` for Drupal 8 users, clear
  // the Drush cache as well, for consistency with that behavior.
  drush_cache_clear_drush();
}

