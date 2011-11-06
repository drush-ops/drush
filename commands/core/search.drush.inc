<?php

function search_drush_command() {
  $items['search-status'] = array(
    'description' => 'Show how many items remain to be indexed out of the total.',
    'drupal dependencies' => array('search'),
    'options' => array(
      'pipe' => 'Display in the format remaining/total for processing by scripts.',
    ),
  );
  $items['search-index'] = array(
    'description' => 'Index the remaining search items without wiping the index.',
    'drupal dependencies' => array('search'),
  );
  $items['search-reindex'] = array(
    'description' => 'Force the search index to be rebuilt.',
    'drupal dependencies' => array('search'),
    'options' => array(
      'immediate' => 'Rebuild the index immediately, instead of waiting for cron.',
    ),
  );
  return $items;
}

function drush_search_status() {
  list($remaining, $total) = _drush_search_status();
  drush_print(dt('There are @remaining items out of @total still to be indexed.', array(
    '@remaining' => $remaining,
    '@total' => $total,
  )));
  drush_print_pipe("$remaining/$total\n");
}

function _drush_search_status() {
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

function drush_search_index() {
  drush_op('_drush_search_index');
  drush_log(dt('The search index has been built.'), 'ok');
}

function _drush_search_index() {
  list($remaining, ) = _drush_search_status();
  register_shutdown_function('search_update_totals');
  while ($remaining > 0) {
    drush_log(dt('Remaining items to be indexed: ' . $remaining), 'ok');
    // Use drush_invoke_process() to start subshell. Avoids out of memory issue.
    $eval = "register_shutdown_function('search_update_totals');";
    if (drush_drupal_major_version() >= 7) {
      // If needed, prod module_implements() to recognize our
      // hook_node_update_index() implementations.
      $implementations = module_implements('node_update_index');
      if (!in_array('system', $implementations)) {
        // Note that this resets module_implements cache.
        module_implements('node_update_index', FALSE, TRUE);
      }

      foreach (variable_get('search_active_modules', array('node', 'user')) as $module) {
        $eval .= " module_invoke('$module', 'update_index');";
      }
    }
    else {
      // If needed, prod module_implements() to recognize our hook_nodeapi()
      // implementations.
      $implementations = module_implements('nodeapi');
      if (!in_array('system', $implementations)) {
        // Note that this resets module_implements cache.
        module_implements('nodeapi', FALSE, TRUE);
      }

      $eval .= " module_invoke_all('update_index');";
    }
    drush_invoke_process('@self', 'php-eval', array($eval));
    list($remaining, ) = _drush_search_status();
  }
}

function drush_search_reindex() {
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
    drush_op('_drush_search_index');
    drush_log(dt('The search index has been rebuilt.'), 'ok');
  }
  else {
    drush_log(dt('The search index will be rebuilt.'), 'ok');
  }
}

/**
 * Fake an implementation of hook_node_update_index() for Drupal 7.
 */
function system_node_update_index($node) {
  // Verbose output.
  if (drush_get_context('DRUSH_VERBOSE')) {
    drush_log(dt('Indexing node !nid.', array('!nid' => $node->nid)), 'ok');
  }
}

/**
 * Fake an implementation of hook_nodeapi() for Drupal 6.
 */
function system_nodeapi(&$node, $op, $a3 = NULL, $a4 = NULL) {
  if ($op == 'update index') {
    // Verbose output.
    if (drush_get_context('DRUSH_VERBOSE')) {
      drush_log(dt('Indexing node !nid.', array('!nid' => $node->nid)), 'ok');
    }
  }
}
