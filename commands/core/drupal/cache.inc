<?php
/**
 * @file
 *   Engine for the cache commands.
 */

function _drush_cache_command_get($cid, $bin) {
  if (drush_drupal_major_version() >=8) {
    $result = cache($bin)->get($cid);
  }
  else {
    $result = cache_get($cid, $bin);
  }
  return $result;
}

function _drush_cache_command_set($cid, $data, $bin, $expire, $tags) {
  // Convert the "expire" argument to a valid value for Drupal's cache_set().
  if ($expire == 'CACHE_TEMPORARY') {
    $expire = CACHE_TEMPORARY;
  }
  if (is_null($expire) || $expire == 'CACHE_PERMANENT') {
    $expire = CACHE_PERMANENT;
  }

  return cache_set($cid, $data, $bin, $expire);
}

function _drush_cache_clear_types($include_bootstrapped_types) {
  $drupal_version = drush_drupal_major_version();

  // D8 renamed menu_rebuild() (https://drupal.org/node/1561492).
  if ($drupal_version >= 8) {
    $types['menu'] = 'menu_router_rebuild';
  }

  if ($drupal_version >= 7) {
    $types['registry'] = 'registry_update';
  }
  elseif ($drupal_version == 6 && function_exists('module_exists') && module_exists('autoload')) {
    // TODO: move this to autoload module.
    $types['registry'] = 'autoload_registry_update';
  }

  // Don't allow "all" cache type on D8 and later - we want users to use the
  // "cache-rebuild" command instead.
  if ($drupal_version <= 7) {
    $types['all'] = 'drush_cache_clear_both';
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
  // We want Drupal 8 users to use the `drush cache-rebuild` command instead.
  if (drush_drupal_major_version() >= 8) {
    return drush_set_error('DRUSH_CC_ALL_DEPRECATED', dt('`clear-cache all` is deprecated for Drupal 8 and later. Please use the `cache-rebuild` command instead.'));
  }
  drush_cache_clear_drush();
  if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    drupal_flush_all_caches();
  }
}

/**
 * Rebuild a Drupal 8 site.
 */
function drush_cache_rebuild() {
  require_once DRUPAL_ROOT . '/core/includes/utility.inc';
  // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it
  // manually.
  drupal_rebuild();
  // As this command replaces `drush cache-clear all` for Drupal 8 users, clear
  // the Drush cache as well, for consistency with that behavior.
  drush_cache_clear_drush();
}
