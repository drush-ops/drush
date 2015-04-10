<?php
/**
 * @file
 *   Engine for the cache commands.
 */

function _drush_cache_command_get($cid, $bin) {
  if (is_null($bin)) {
    $bin = _drush_cache_bin_default();
  }
  return cache_get($cid, $bin);
}

/**
 * The default bin.
 *
 * @return string
 */
function _drush_cache_bin_default() {
  return 'cache';
}

function _drush_cache_command_set($cid, $data, $bin, $expire, $tags) {
  // Convert the "expire" argument to a valid value for Drupal's cache_set().
  if (is_null($bin)) {
    $bin = _drush_cache_bin_default();
  }
  if ($expire == 'CACHE_TEMPORARY') {
    $expire = CACHE_TEMPORARY;
  }
  if (!isset($expire) || $expire == 'CACHE_PERMANENT') {
    $expire = CACHE_PERMANENT;
  }

  // D6/D7 don't natively support cache tags.
  return cache_set($cid, $data, $bin, $expire);
}

function _drush_cache_clear_types($include_bootstrapped_types) {
  $types = array(
    'drush' => 'drush_cache_clear_drush',
    'all' => 'drush_cache_clear_both',
  );
  if ($include_bootstrapped_types) {
    $types += array(
      'theme-registry' => 'drush_cache_clear_theme_registry',
      'menu' => 'menu_rebuild',
      'css-js' => 'drush_cache_clear_css_js',
      'block' => 'drush_cache_clear_block',
      'module-list' => 'drush_get_modules',
      'theme-list' => 'drush_get_themes',
    );
  }
  $drupal_version = drush_drupal_major_version();

  if ($drupal_version >= 7) {
    $types['registry'] = 'registry_update';
  }
  elseif ($drupal_version == 6 && function_exists('module_exists') && module_exists('autoload')) {
    // TODO: move this to autoload module.
    $types['registry'] = 'autoload_registry_update';
  }

  return $types;
}

function drush_cache_clear_theme_registry() {
  if (drush_drupal_major_version() >= 7) {
    drupal_theme_rebuild();
  }
  else {
    cache_clear_all('theme_registry', 'cache', TRUE);
  }
}

function drush_cache_clear_menu() {
  return menu_router_rebuild();
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
 * Clear caches internal to Drush core and Drupal.
 */
function drush_cache_clear_both() {
  drush_cache_clear_drush();
  if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    drupal_flush_all_caches();
  }
}
