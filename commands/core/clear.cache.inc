<?php

/**
 * Command callback for drush cache-clear.
 */
 function drush_core_cache_clear($type = NULL) {
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
      if ($type) {
        drush_op('call_user_func', $types[$type]);
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

function drush_cache_clear_types() {
  $types = array(
    'all' => 'drupal_flush_all_caches',
    'theme' => 'drush_cache_clear_theme_registry',
    'menu' => 'menu_rebuild',
    'css+js' => 'drush_cache_clear_css_js',
  );
  if (count(module_implements('node_grants'))) {
    $types['nodeaccess'] = 'node_access_rebuild';
  }
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
