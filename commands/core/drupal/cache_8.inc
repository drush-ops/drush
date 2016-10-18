<?php
/**
 * @file
 *   Engine for the cache commands.
 */

use Drupal\Core\Cache\Cache;

function _drush_cache_command_get($cid, $bin) {
  if (is_null($bin)) {
    $bin = _drush_cache_bin_default();
  }
  return \Drupal::cache($bin)->get($cid);
}

/**
 * The default bin.
 *
 * @return string
 */
function _drush_cache_bin_default() {
  return 'default';
}

function _drush_cache_command_set($cid, $data, $bin, $expire, $tags) {
  if (is_null($bin)) {
    $bin = _drush_cache_bin_default();
  }

  // Convert the "expire" argument to a valid value for Drupal's cache_set().
  if ($expire == 'CACHE_TEMPORARY') {
    $expire = Cache::TEMPORARY;
  }
  if (!isset($expire) || $expire == 'CACHE_PERMANENT') {
    $expire = Cache::PERMANENT;
  }

  return \Drupal::cache($bin)->set($cid, $data, $expire, $tags);
}

function _drush_cache_clear_types($include_bootstrapped_types) {
  $types = array(
    'drush' => 'drush_cache_clear_drush',
  );
  if ($include_bootstrapped_types) {
    $types += array(
      'theme-registry' => 'drush_cache_clear_theme_registry',
      'router' => 'drush_cache_clear_router',
      'css-js' => 'drush_cache_clear_css_js',
      'module-list' => 'drush_get_modules',
      'theme-list' => 'drush_get_themes',
      'render' => 'drush_cache_clear_render',
    );
  }
  return $types;
}

function drush_cache_clear_theme_registry() {
  \Drupal::service('theme.registry')->reset();
}

function drush_cache_clear_router() {
  /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
  $router_builder = \Drupal::service('router.builder');
  $router_builder->rebuild();
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
  // There is no distinct block cache in D8. See https://github.com/drush-ops/drush/issues/1531.
  // \Drupal::cache('block')->deleteAll();
}

/**
 * Clears the render cache entries.
 */
function drush_cache_clear_render() {
  Cache::invalidateTags(['rendered']);
}
