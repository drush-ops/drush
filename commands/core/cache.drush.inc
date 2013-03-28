<?php

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
    'options' => array(
      'format' => 'Format to output the object. Use "print_r" for print_r (default), "export" for var_export, and "json" for JSON.',
    ),
    'callback' => 'drush_cache_command_get',
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
    ),
    'required-arguments' => 2,
    'options' => array(
      'format' => 'Format to parse the object. Use "string" for string (default), and "json" for JSON.',
      'cache-get' => 'If the object is the result a previous fetch from the cache, only store the value in the "data" property of the object in the cache.',
    ),
    'callback' => 'drush_cache_command_set',
    'aliases' => array('cs'),
  );

  return $items;
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Array of clear types.
 */
function cache_cache_command_clear_complete() {
  return array('values' => array_keys(drush_cache_clear_types(TRUE)));
}

/**
 * Command callback for drush cache-clear.
 */
function drush_cache_command_clear($type = NULL) {
  $types = drush_cache_clear_types(drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));

  // Check if the provided type ($type) is a valid cache type.
  if ($type && !key_exists($type, $types)) {
    // If we haven't done a full bootstrap, provide a more
    // specific message with instructions to the user on
    // bootstrapping a Drupal site for more options.
    if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $all_types = drush_cache_clear_types(TRUE);
      if (key_exists($type, $all_types)) {
        return drush_set_error(dt("'!type' cache requires a working Drupal site to operate on. Use the --root and --uri options, or a site @alias, or cd to a directory containing a Drupal settings.php file.", array('!type' => $type)));
      }
      else {
        return drush_set_error(dt("'!type' cache is not a valid cache type. There may be more cache types available if you select a working Drupal site.", array('!type' => $type)));
      }
    }
    return drush_set_error(dt("'!type' cache is not a valid cache type.", array('!type' => $type)));
  }

  if ($type) {
    drush_op($types[$type]);
    if ($type == 'all' && !drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $type = 'drush';
    }
  }
  else {
    // Don't offer 'all' unless Drush has bootstrapped the Drupal site
    if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      unset($types['all']);
    }
    $type = drush_choice($types, 'Enter a number to choose which cache to clear.', '!key');
    if ($type !== FALSE) {
      call_user_func($types[$type]);
    }
  }
  if ($type !== FALSE) {
    $site_label = '';
    if ($type != 'drush') {
      $self_name = drush_sitealias_bootstrapped_site_name();
      if (isset($self_name)) {
        $site_label = dt(' in !name', array('!name' => $self_name));
      }
    }
    drush_log(dt("'!name' cache was cleared!insitename", array('!name' => $type, '!insitename' => $site_label)), 'success');
  }
}

/**
 * Print an object returned from the cache.
 *
 * @param $cid
 *   The cache ID of the object to fetch.
 * @param $bin
 *   Optional parameter to specify a specific bin to fetch from.
 */
function drush_cache_command_get($cid = NULL, $bin = NULL) {
  if (!$cid) {
    drush_log(dt('You must specify a cache id to fetch.'), 'error');
    return;
  }

  if (!$bin) {
    $bin = 'cache';
  }

  $result = cache_get($cid, $bin);
  if (!empty($result)) {
    drush_print(drush_format($result));
  }
  else {
    drush_log(dt('The !cid object in the !bin cache bin was not found.', array('!cid' => $cid, '!bin' => $bin)), 'error');
  }
}

/**
 * Set an object in the cache.
 *
 * @param $cid
 *   The cache ID of the object to fetch.
 * @param $data
 *   The data to save to the cache, or '-' to read from STDIN.
 * @param $bin
 *   Optional parameter to specify a specific bin to fetch from.
 * @param $expire
 *   Optional parameter to specify the expiry of the cached object.
 */
function drush_cache_command_set($cid = NULL, $data = '', $bin = NULL, $expire = CACHE_PERMANENT) {
  if (!$bin) {
    $bin = 'cache';
  }

  if ($data == '-') {
    $data = stream_get_contents(STDIN);
  }

  // Now, we parse the object.
  switch (drush_get_option('format', 'string')) {
    case 'json':
      $data = drush_json_decode($data);
      break;
  }

  if (drush_get_option('cache-get')) {
    $data = $data->data;
  }

  cache_set($cid, $data, $bin, $expire);
}

/*
 * All types of caches available for clearing. Contrib commands can alter in their own.
 */
function drush_cache_clear_types($include_bootstraped_types = FALSE) {
  $types = array(
    'all' => 'drush_cache_clear_both',
    'drush' => 'drush_cache_clear_drush',
  );
  if ($include_bootstraped_types) {
    $types += array(
      'theme-registry' => 'drush_cache_clear_theme_registry',
      'menu' => 'menu_rebuild',
      'css-js' => 'drush_cache_clear_css_js',
      'block' => 'drush_cache_clear_block',
      'module-list' => 'drush_get_modules',
      'theme-list' => 'drush_get_themes',
    );
  }

  if (drush_drupal_major_version() >= 7) {
    $types['registry'] = 'registry_update';
  }
  elseif (drush_drupal_major_version() == 6 && function_exists('module_exists') && module_exists('autoload')) {
    // TODO: move this to autoload module.
    $types['registry'] = 'autoload_registry_update';
  }

  // Include the appropriate environment engine, so callbacks can use core
  // version specific cache clearing functions directly.
  drush_include_engine('drupal', 'environment');

  // Command files may customize $types as desired.
  drush_command_invoke_all_ref('drush_cache_clear', $types);

  return $types;
}

function drush_cache_clear_theme_registry() {
  if (drush_drupal_major_version() >= 7) {
    drupal_theme_rebuild();
  }
  else {
    drush_db_delete('cache', 'cid LIKE :theme_registry', array(':theme_registry' => 'theme_registry%'));
  }
}

function drush_cache_clear_css_js() {
  _drupal_flush_css_js();
  drupal_clear_css_cache();
  drupal_clear_js_cache();
}

/**
 * Clear the cache of the block output.
 */
function drush_cache_clear_block() {
  cache_clear_all(NULL, 'cache_block');
}

/**
 * Clear caches internal to drush core.
 */
function drush_cache_clear_drush() {
  drush_cache_clear_all(NULL, 'default'); // commandfiles, etc.
  drush_cache_clear_all(NULL, 'complete'); // completion
}

/**
 * Clear caches internal to Drush core and Drupal.
 */
function drush_cache_clear_both() {
  drush_cache_clear_drush();
  if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    drupal_flush_all_caches();
  }
}
