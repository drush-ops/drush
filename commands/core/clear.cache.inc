<?php

/**
 * Command callback for drush cache clear.
 */
 function drush_core_cache_clear() {
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
      drupal_flush_all_caches();
      drush_print(dt('Cache cleared.'));
      break;
  }
}