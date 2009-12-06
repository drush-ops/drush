<?php

function watchdog_drush_help($section) {
  switch ($section)  {
    case 'drush:watchdog show':
      return dt("Show recent watchdog messages.");
    case 'drush:watchdog delete':
      return dt("Delete watchdog messages.");
  }
}

function watchdog_drush_command() {
  $items['watchdog show'] = array(
    'description' => 'Shows recent watchdog log messages. Optionally filter for a specific type.',
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'arguments' => array(
      'type' => 'The type of messages to show. Defaults to all.',
    ),
    'options' => array(
      '--limit' => 'The number of messages to show. Defaults to 10.',
      '--severity' => 'Restrict to a given severity level. Error=0, Warning=4.',
    ),
    'examples' => array(
      'watchdog show cron' => 'Show recent cron watchdog messages.',
      'watchdog show --severity=0' => 'Show recent error messages.',
      'watchdog show --limit=50' => 'Show 50 recent watchdog messages.',
    ),
    'aliases' => array('ws'),
  );
  $items['watchdog delete'] = array(
    'description' => 'Delete all messages or only those of a specified type.',
    'arguments' => array(
      'type' => 'The type of messages to delete. Use \'all.\' to do a complete wipe.',
    ),
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'examples' => array(
      'watchdog delete all' => 'Delete all watchdog messages.',
      'watchdog delete cron' => 'Delete all cron watchdog messages.',
    ),
    'aliases' => array('wd'),
  );
  return $items;
}

/**
 * Displays the most recent watchdog log messages (default: 10 messages).
 */
function drush_core_watchdog_show($type = NULL) {
  $limit = drush_get_option('limit') ? drush_get_option('limit') : 10;
  $severity = drush_get_option('severity');

  switch (drush_drupal_major_version()) {
    case 5:
    case 6:
      if ($type) {
        $where[] = "w.type = '%s'";
        $placeholders[] = $type;
      }
      if ($severity) {
        $where[] = "w.severity = %d";
        $placeholders[] = $severity;
      }
      $criteria = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);
      $sql = 'SELECT w.*, u.name, u.uid FROM {watchdog} w INNER JOIN {users} u ON w.uid = u.uid  ';
      $sort = ' ORDER BY w.wid DESC';
      $result = db_query_range($sql . $criteria . $sort, $placeholders, 0, $limit);
      while ($watchdog = db_fetch_object($result)) {
        $rows[] = core_watchdog_format_row($watchdog);
      }
      break;
    default:
      $query = db_select('watchdog', 'w')
                ->fields('w')
                ->fields('u', array('name', 'uid'))
                ->orderBy('w.wid', 'DESC')
                ->range(0, $limit);
      $query->join('users', 'u', 'w.uid = u.uid');
      if ($type) {
        $query->condition('w.type', $type);
      }
      if ($severity) {
        $query->condition('w.severity', $severity);
      }
      $results = $query->execute();
      $watchdogs = $results->fetchAllAssoc('wid');
      foreach ($watchdogs as $watchdog) {
        $rows[] = core_watchdog_format_row($watchdog);
      }
  }

  if (empty($rows)) {
    return drush_log('No log messages available.', 'ok');
  }
  else {
    drush_log(dt('Last !count watchdog log messages:', array('!count' => $limit)));

    array_unshift($rows, array(dt('Date'), dt('Severity'), dt('Type'), dt('Message'), dt('User')));
    drush_print_table($rows, TRUE);
  }
}

function core_watchdog_format_row($watchdog) {
  $severities = watchdog_severity_levels();
  return array(
    format_date($watchdog->timestamp, 'small'),
    $severities[$watchdog->severity],
    dt($watchdog->type),
    core_watchdog_format_message($watchdog),
    strip_tags(theme('username', (array)$watchdog)),
  );
}

function core_watchdog_format_message($watchdog) {
  if (drush_drupal_major_version() == 5) {
    $message = $watchdog->message;
  }
  else {
    $variables = unserialize($watchdog->variables);
    $message = is_array($variables) ? strtr($watchdog->message, $variables) : $watchdog->message;
  }
  return truncate_utf8(decode_entities($message), 68, FALSE, FALSE);
}

/**
 * Deletes all log messages of a certain type from the watchdog log
 * (default: all).
 */
function drush_core_watchdog_delete($type = NULL) {
  if ($type == "all") {
    // D7: ought to be a dynamic query.
    drush_op('db_query', 'DELETE FROM {watchdog}'); // Indiscriminately delete all
    drush_log(dt('Deleted all rows.'), 'ok');
  }
  elseif (!empty($type)) {
    drush_op('db_query', 'DELETE FROM {watchdog} WHERE type = \'%s\'', $type);
    drush_log(dt('Deleted all rows.'), 'ok');
  }
  else {
    drush_set_error(dt('Please specify a message type, or "all" to delete all messages.'));
  }
}