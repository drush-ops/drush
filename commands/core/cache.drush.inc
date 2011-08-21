<?php

/**
 * Implementation of hook_drush_command().
 */
function cache_drush_command() {
  $items = array();

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
    'options' => array(
      'format' => 'Format to output the object. Use "print_r" for print_r (default), "export" for var_export, and "json" for JSON.',
    ),
    'aliases' => array('cg'),
  );
  $items['cache-clear'] = array(
    'description' => 'Clear a specific cache, or all drupal caches.',
    'arguments' => array(
      'type' => 'The particular cache to clear. Omit this argument to choose from available caches.',
    ),
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
    'options' => array(
      'format' => 'Format to parse the object. Use "string" for string (default), and "json" for JSON.',
      'cache-get' => 'If the object is the result a previous fetch from the cache, only store the value in the "data" property of the object in the cache.',
    ),
    'aliases' => array('cs'),
  );

  return $items;
}

/**
 * Command callback for drush cache-clear.
 */
function drush_cache_clear($type = NULL) {
  switch (drush_drupal_major_version()) {
    case 5:
      // clear preprocessor cache
      drupal_clear_css_cache();

      // clear core tables
      $core = array('cache', 'cache_filter', 'cache_menu', 'cache_page');
      $alltables = array_merge($core, module_invoke_all('devel_caches'));
      foreach ($alltables as $table) {
        cache_clear_all('*', $table, TRUE);
      }
      drush_print(dt('Cache cleared.'));
      break;
    case 6:
    case 7:
    default:
      $types = drush_cache_clear_types();
      // Check if the provided type ($type) is a valid cache type.
      if ($type && !key_exists($type, $types)) {
        return drush_set_error(dt("'!type' cache is not a valid cache type", array('!type' => $type)));
      }

      if ($type) {
        drush_op($types[$type]);
        drush_log(dt("'!name' cache was cleared", array('!name' => $type)), 'success');
      }
      else {
        $choice = drush_choice($types, 'Enter a number to choose which cache to clear.', '!key');
        if ($choice !== FALSE) {
          call_user_func($types[$choice]);
          drush_log(dt("'!name' cache was cleared", array('!name' => $choice)), 'success');
        }
      }
      break;
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
function drush_cache_get($cid = NULL, $bin = NULL) {
  if (!$cid) {
    drush_log(dt('You must specify a cache id to fetch.'), 'error');
    return;
  }

  if (!$bin) {
    $bin = 'cache';
  }

  $result = cache_get($cid, $bin);
  if (!empty($result)) {
    switch (drush_get_option('format', 'print_r')) {
      case 'export':
        drush_print(var_export($result, TRUE));
        return;
      case 'json':
        drush_print(drush_json_encode($result));
        return;
      case 'print_r':
      default:
        drush_print_r($result);
        return;
    }
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
function drush_cache_set($cid = NULL, $data = '', $bin = NULL, $expire = CACHE_PERMANENT) {
  if (!$cid) {
    drush_log(dt('You must specify a cache id to set.'), 'error');
    return;
  }

  if ($data === '') {
    drush_log(dt('The data to set must be non-empty.'), 'error');
    return;
  }

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

  switch(drush_drupal_major_version()) {
    case 5:
      cache_set($cid, $bin, $data, $expire);
      break;
    case 6:
    case 7:
    default:
      cache_set($cid, $data, $bin, $expire);
  }
}

function drush_cache_clear_types() {
  $types = array(
    'all' => 'drupal_flush_all_caches',
    'theme registry' => 'drush_cache_clear_theme_registry',
    'menu' => 'menu_rebuild',
    'css+js' => 'drush_cache_clear_css_js',
    'block' => 'drush_cache_clear_block',
    'module list' => 'drush_get_modules',
    'theme list' => 'drush_get_themes',
  );
  if (drush_drupal_major_version() >= 7) {
    $types['registry'] = 'registry_update';
  }
  elseif (drush_drupal_major_version() == 6 && module_exists('autoload')) {
    // TODO: move this to autoload module.
    $types['registry'] = 'autoload_registry_update';
  }
  if (count(module_implements('node_grants'))) {
    $types['nodeaccess'] = 'node_access_rebuild';
  }

  // Include the appropriate environment engine, so callbacks can use core
  // version specific cache clearing functions directly.
  drush_include_engine('drupal', 'environment');

  // Command files may customize $types as desired.
  drush_command_invoke_all_ref('drush_cache_clear', $types);
  return $types;
}

function drush_cache_clear_theme_registry() {
  drush_db_delete('cache', 'cid LIKE :theme_registry', array(':theme_registry' => 'theme_registry%'));
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

