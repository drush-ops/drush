<?php

function drush_core_search_status() {
  list($remaining, $total) = _drush_core_search_status();
  drush_print(dt('There are @remaining items out of @total still to be indexed.', array(
    '@remaining' => $remaining,
    '@total' => $total,
  )));
  drush_print_pipe("$remaining/$total\n");
}

function _drush_core_search_status() {
  $remaining = 0;
  $total = 0;
  if (drush_drupal_major_version() >= 7) {
    foreach (module_implements('search_status') as $module) {
      $status = module_invoke($module, 'search_status');
      $remaining += $status['remaining'];
      $total += $status['total'];
    }
  }
  else {
    foreach (module_implements('search') as $module) {
      // Special case. Apachesolr recommends disabling core indexing with
      // search_cron_limit = 0. Need to avoid infinite status loop.
      if ($module == 'node' && variable_get('search_cron_limit', 10) == 0) {
        continue;
      }
      $status = module_invoke($module, 'search', 'status');
      $remaining += $status['remaining'];
      $total += $status['total'];
    }
  }
  return array($remaining, $total);
}

function drush_core_search_index() {
  drush_print(dt("Building the index may take a long time."));
  if (!drush_confirm(dt('Do you really want to continue?'))) {
    return drush_user_abort();
  }
  drush_op('_drush_core_search_index');
  drush_log(dt('The search index has been built.'), 'ok');
}

function _drush_core_search_index() {
  list($remaining, ) = _drush_core_search_status();
  register_shutdown_function('search_update_totals');
  while ($remaining > 0) {
    drush_log(dt('Remaining items to be indexed: ' . $remaining), 'ok');
    // Use drush_backend_invoke() to start subshell. Avoids out of memory issue.
    $eval = "register_shutdown_function('search_update_totals');";
    if (drush_drupal_major_version() >= 7) {
      foreach (variable_get('search_active_modules', array('node', 'user')) as $module) {
        $eval .= " module_invoke($module, 'update_index');";
      }
    }
    else {
      $eval .= " module_invoke_all('update_index');";
    }
    drush_backend_invoke('php-eval', array($eval));
    list($remaining, ) = _drush_core_search_status();
  }
}

function drush_core_search_reindex() {
  drush_print(dt("The search index must be fully rebuilt before any new items can be indexed."));
  if (drush_get_option('immediate')) {
    drush_print(dt("Rebuilding the index may take a long time."));
  }
  if (!drush_confirm(dt('Do you really want to continue?'))) {
    return drush_user_abort();
  }

  if (drush_drupal_major_version() >= 7) {
    drush_op('search_reindex');
  }
  else {
    drush_op('search_wipe');
  }
  if (drush_get_option('immediate')) {
    drush_op('_drush_core_search_index');
    drush_log(dt('The search index has been rebuilt.'), 'ok');
  }
  else {
    drush_log(dt('The search index will be rebuilt.'), 'ok');
  }
}
