<?php

use Drush\Log\LogLevel;

/**
 * Implementation of hook_drush_help().
 */
function search_drush_help($section) {
  switch ($section) {
    case 'meta:search:title':
      return dt('Search commands');
    case 'meta:search:summary':
      return dt('Interact with Drupal\'s core search system.');
  }
}

function search_drush_command() {
  $items['search-status'] = array(
    'description' => 'Show how many items remain to be indexed out of the total.',
    'drupal dependencies' => array('search'),
    'outputformat' => array(
      'default' => 'message',
      'pipe-format' => 'message',
      'field-labels' => array('remaining' => 'Items not yet indexed', 'total' => 'Total items'),
      'message-template' => 'There are !remaining items out of !total still to be indexed.',
      'pipe-metadata' => array(
        'message-template' => '!remaining/!total',
      ),
      'output-data-type' => 'format-list',
      'aliases' => array('search:status'),
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
    'aliases' => array('search:index'),
  );
  return $items;
}

function drush_search_status() {
  list($remaining, $total) = _drush_search_status();
  return array(
    'remaining' => $remaining,
    'total' => $total,
  );
}

function _drush_search_status() {
  $remaining = 0;
  $total = 0;
  if (drush_drupal_major_version() >= 8) {
    $search_page_repository = \Drupal::service('search.search_page_repository');
    foreach ($search_page_repository->getIndexableSearchPages() as $entity) {
      $status = $entity->getPlugin()->indexStatus();
      $remaining += $status['remaining'];
      $total += $status['total'];
    }
  }
  elseif (drush_drupal_major_version() == 7) {
    foreach (variable_get('search_active_modules', array('node', 'user')) as $module) {
      drush_include_engine('drupal', 'environment');
      $status = drush_module_invoke($module, 'search_status');
      $remaining += $status['remaining'];
      $total += $status['total'];
    }
  }
  else {
    drush_include_engine('drupal', 'environment');
    foreach (drush_module_implements('search') as $module) {
      // Special case. Apachesolr recommends disabling core indexing with
      // search_cron_limit = 0. Need to avoid infinite status loop.
      if ($module == 'node' && variable_get('search_cron_limit', 10) == 0) {
        continue;
      }
      $status = drush_module_invoke($module, 'search', 'status');
      if (isset($status['remaining']) && isset($status['total'])) {
        $remaining += $status['remaining'];
        $total += $status['total'];
      }
    }
  }
  return array($remaining, $total);
}

function drush_search_index() {
  drush_op('_drush_search_index');
  drush_log(dt('The search index has been built.'), LogLevel::OK);
}

function _drush_search_index() {
  list($remaining, $total) = _drush_search_status();
  register_shutdown_function('search_update_totals');
  $failures = 0;
  while ($remaining > 0) {
    $done = $total - $remaining;
    $percent = $done / $total * 100;
    drush_log(dt('!percent complete. Remaining items to be indexed: !count', array('!percent' => number_format($percent, 2), '!count' => $remaining)), LogLevel::OK);
    $eval = "register_shutdown_function('search_update_totals');";

    // Use drush_invoke_process() to start subshell. Avoids out of memory issue.
    if (drush_drupal_major_version() >= 8) {
      $eval = "drush_module_invoke('search', 'cron');";
    }
    elseif (drush_drupal_major_version() == 7) {
      // If needed, prod drush_module_implements() to recognize our
      // hook_node_update_index() implementations.
      drush_include_engine('drupal', 'environment');
      $implementations = drush_module_implements('node_update_index');
      if (!in_array('system', $implementations)) {
        // Note that this resets module_implements cache.
        drush_module_implements('node_update_index', FALSE, TRUE);
      }

      foreach (variable_get('search_active_modules', array('node', 'user')) as $module) {
        // TODO: Make sure that drush_module_invoke is really available when doing this eval().
        $eval .= " drush_module_invoke('$module', 'update_index');";
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
    $previous_remaining = $remaining;
    list($remaining, ) = _drush_search_status();
    // Make sure we're actually making progress.
    if ($remaining == $previous_remaining) {
      $failures++;
      if ($failures == 3) {
        drush_log(dt('Indexing stalled with @number items remaining.', array(
          '@number' => $remaining,
        )), LogLevel::ERROR);
        return;
      }
    }
    // Only count consecutive failures.
    else {
      $failures = 0;
    }
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

  if (drush_drupal_major_version() == 8) {
    // D8 CR: https://www.drupal.org/node/2326575
    $search_page_repository = \Drupal::service('search.search_page_repository');
    foreach ($search_page_repository->getIndexableSearchPages() as $entity) {
      $entity->getPlugin()->markForReindex();
    }
  }
  elseif (drush_drupal_major_version() == 7) {
    drush_op('search_reindex');
  }
  else {
    drush_op('search_wipe');
  }

  if (drush_get_option('immediate')) {
    drush_op('_drush_search_index');
    drush_log(dt('The search index has been rebuilt.'), LogLevel::OK);
  }
  else {
    drush_log(dt('The search index will be rebuilt.'), LogLevel::OK);
  }
}

/**
 * Fake an implementation of hook_node_update_index() for Drupal 7.
 */
function system_node_update_index($node) {
  // Verbose output.
  if (drush_get_context('DRUSH_VERBOSE')) {
    $nid = $node->nid;
    if (is_object($nid)) {
      // In D8, this is a FieldItemList.
      $nid = $nid->value;
    }

    drush_log(dt('Indexing node !nid.', array('!nid' => $nid)), LogLevel::OK);
  }
}

/**
 * Fake an implementation of hook_nodeapi() for Drupal 6.
 */
function system_nodeapi(&$node, $op, $a3 = NULL, $a4 = NULL) {
  if ($op == 'update index') {
    // Verbose output.
    if (drush_get_context('DRUSH_VERBOSE')) {
      drush_log(dt('Indexing node !nid.', array('!nid' => $node->nid)), LogLevel::OK);
    }
  }
}
