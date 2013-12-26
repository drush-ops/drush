<?php
/**
 * @file
 *   Engine for the cache commands.
 */

use Drupal\Core\Cache\CacheBackendInterface;

function _drush_cache_command_get($cid, $bin) {
  return cache($bin)->get($cid);
}

function _drush_cache_command_set($cid, $data, $bin, $expire, $tags) {
  // Convert the "expire" argument to a valid value for Drupal's cache_set().
  if ($expire == 'CACHE_TEMPORARY') {
    $expire = CacheBackendInterface::CACHE_TEMPORARY;
  }
  if (is_null($expire) || $expire == 'CACHE_PERMANENT') {
    $expire = CacheBackendInterface::CACHE_PERMANENT;
  }

  return cache($bin)->set($cid, $data, $expire, $tags);
}

function _drush_cache_clear_types($include_bootstrapped_types) {
  $types = array(
    'drush' => 'drush_cache_clear_drush',
  );
  if ($include_bootstrapped_types) {
    $types += array(
      'theme-registry' => 'drush_cache_clear_theme_registry',
      'menu' => 'menu_router_rebuild',
      'css-js' => 'drush_cache_clear_css_js',
      'block' => 'drush_cache_clear_block',
      'module-list' => 'drush_get_modules',
      'theme-list' => 'drush_get_themes',
    );
  }
  return $types;
}

function drush_cache_clear_theme_registry() {
  \Drupal::service('theme.registry')->reset();
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
  cache('block')->deleteAll();
}
