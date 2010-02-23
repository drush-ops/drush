<?php

/**
 * Implementation of hook_drush_help().
 */
function watchdog_drush_help($section) {
  switch ($section)  {
    case 'drush:watchdog-list':
      return dt('Show available message types and severity levels. A prompt will ask for a choice to show watchdog messages.');
    case 'drush:watchdog-show':
      return dt('Show watchdog messages. Arguments and options can be combined to configure which messages to show.');
    case 'drush:watchdog-delete':
      return dt('Delete watchdog messages. Arguments or options must be provided to specify which messages to delete.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function watchdog_drush_command() {
  $items['watchdog-list'] = array(
    'description' => 'Show available message types and severity levels. A prompt will ask for a choice to show watchdog messages.',
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'aliases' => array('wd-list'),
  );
  $items['watchdog-show'] = array(
    'description' => 'Show watchdog messages.',
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'arguments' => array(
      'wid' => 'Optional id of a watchdog message to show in detail. If not provided, a listing of most recent 10 messages will be displayed. Alternatively if a string is provided, watchdog messages will be filtered by it.',
    ),
    'options' => array(
      '--count' => 'The number of messages to show. Defaults to 10.',
      '--severity' => 'Restrict to messages of a given severity level.',
      '--type' => 'Restrict to messages of a given type.',
    ),
    'examples' => array(
      'watchdog-show' => 'Show a listing of most recent 10 messages.',
      'watchdog-show 64' => 'Show in detail message with id 64.',
      'watchdog-show "cron run succesful"' => 'Show a listing of most recent 10 messages containing the string "cron run succesful".',
      'watchdog-show --count=46' => 'Show a listing of most recent 46 messages.',
      'watchdog-show --severity=notice' => 'Show a listing of most recent 10 messages with a severity of notice.',
      'watchdog-show --type=php' => 'Show a listing of most recent 10 messages of type php.',
    ),
    'aliases' => array('wd-show', 'ws'),
  );
  $items['watchdog-delete'] = array(
    'description' => 'Delete watchdog messages.',
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'arguments' => array(
      '--severity' => 'Delete messages of a given severity level.',
      '--type' => 'Delete messages of a given type.',
    ),
    'examples' => array(
      'watchdog-delete all' => 'Delete all messages.',
      'watchdog-delete 64' => 'Delete messages with id 64.',
      'watchdog-delete "cron run succesful"' => 'Delete messages containing the string "cron run succesful".',
      'watchdog-delete --severity=notice' => 'Delete all messages with a severity of notice.',
      'watchdog-delete --type=cron' => 'Delete all messages of type cron.',
    ),
    'aliases' => array('wd-del', 'wd-delete'),
    'deprecated-aliases' => array('wd'),
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_core_watchdog_list() {
  $options['-- types --'] = dt('== message types ==');
  $types = core_watchdog_message_types();
  foreach ($types as $type) {
    $options[] = $type;
  }
  $options['-- levels --'] = dt('== severity levels ==');
  $severities = core_watchdog_severity_levels();
  foreach ($severities as $key => $value) {
    $options[] = "$value($key)";
  }
  $option = drush_choice($options, dt('Select a message type or severity level.'));
  if ($option === FALSE) {
    return drush_log(dt('Aborting.'));
  }
  $ntypes = count($types);
  if ($option < $ntypes) {
    drush_set_option('type', $types[$option]);
  }
  else {
    drush_set_option('severity', $option - $ntypes);
  }
  drush_core_watchdog_show_many();
}

/**
 * Command callback.
 */
function drush_core_watchdog_show($arg = NULL) {
  if (is_numeric($arg)) {
    drush_core_watchdog_show_one($arg);
  }
  else {
    drush_core_watchdog_show_many($arg);
  }
}

/**
 * Print a watchdog message.
 *
 * @param $wid
 *    The id of the message to show.
 */
function drush_core_watchdog_show_one($wid) {
  $rsc = drush_db_select('watchdog', '*', 'wid = :wid', array(':wid' => $wid), 0, 1);
  $result = drush_db_fetch_object($rsc);
  if (!$result) {
    return drush_set_error(dt('Watchdog message #!wid not found.', array('!wid' => $wid)));
  }
  $result = core_watchdog_format_result($result);
  foreach ($result as $key => $value) {
    $uc = ucfirst($key);
    $upper->$uc = $value;
  }
  drush_print_table(drush_key_value_to_array_table($upper));
  print "\n";
}

/**
 * Print a table of watchdog messages.
 *
 * @param $filter
 *   String to filter the message's text by.
 */
function drush_core_watchdog_show_many($filter = NULL) {
  $count = drush_get_option('count', 10);
  $type = drush_get_option('type');
  $severity = drush_get_option('severity');

  $where = core_watchdog_query($type, $severity, $filter);
  if ($where === FALSE) {
    return drush_log(dt('Aborting.'));
  }
  $rsc = drush_db_select('watchdog', '*', $where['where'], $where['args'], 0, $count, 'wid', 'DESC');
  if ($rsc === FALSE) {
    return drush_log(dt('Aborting.'));
  }
  $table[] = array(dt('Id'), dt('Date'), dt('Severity'), dt('Type'), dt('Message'));
  while ($result = drush_db_fetch_object($rsc)) {
    $row = core_watchdog_format_result($result);
    $table[] = array($row->wid, $row->date, $row->severity, $row->type, $row->message);
  }
  if (count($table) == 1) {
    return drush_log(dt('No log messages available.'), 'ok');
  }
  else {
    drush_log(dt('Most recent !count watchdog log messages:', array('!count' => $count)));
    drush_print_table($table, TRUE);
    print "\n";
  }
}


/**
 * Format a watchdog database row.
 *
 * @param $result
 *   Array. A database result object.
 * @return
 *   Array. The result object with some attributes themed.
 */
function core_watchdog_format_result($result) {
  // severity
  $severities = core_watchdog_severity_levels();
  $result->severity = $severities[$result->severity];
  // date
  $result->date = format_date($result->timestamp, 'custom', 'd/M H:i');
  unset($result->timestamp);
  // message
  if (drush_drupal_major_version() > 5) {
    $variables = unserialize($result->variables);
    $result->message = strtr($result->message, $variables);
    unset($result->variables);
  }
  $result->message = truncate_utf8(strip_tags(decode_entities($result->message)), 188, FALSE, FALSE);
  // user
  $result->uid = $result->uid ? strip_tags(theme('username', (array)$result)) : dt('Anonymous');
  // possible empty values
  if (empty($result->link)) {
    unset($result->link);
  }
  if (empty($result->referer)) {
    unset($result->referer);
  }

  return $result;
}

/**
 * Command callback.
 *
 * @param $arg
 *   The id of the message to delete or 'all'.
 */
function drush_core_watchdog_delete($arg = NULL) {
  if ($arg == 'all') {
    drush_print(dt('All watchdog messages will be deleted.'));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_log(dt('Aborting.'));
    }
    $affected_rows = drush_db_delete('watchdog');
    drush_log(dt('!affected watchdog messages have been deleted.', array('!affected' => $affected_rows)), 'ok');
  }
  else if (is_numeric($arg)) {
    drush_print(dt('Watchdog message #!wid will be deleted.', array('!wid' => $arg)));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_log(dt('Aborting.'));
    }
    $affected_rows = drush_db_delete('watchdog', 'wid=:wid', array(':wid' => $arg));
    if ($affected_rows == 1) {
      drush_log(dt('Watchdog message #!wid has been deleted.', array('!wid' => $arg)), 'ok');
    }
    else {
      return drush_set_error(dt('Watchdog message #!wid does not exist.', array('!wid' => $arg)));
    }
  }
  else {
    $type = drush_get_option('type');
    $severity = drush_get_option('severity');
    if ((is_null($arg))&&(is_null($type))&&(is_null($severity))) {
      return drush_set_error(dt('No options provided.'));
    }
    $where = core_watchdog_query($type, $severity, $filter, 'OR');
    if ($where === FALSE) {
      return drush_log(dt('Aborting.'), 'error');
    }
    drush_print(dt('All messages matching "!where" will be deleted.', array('!where' => strtr($where['where'], $where['args']))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_log(dt('Aborting.'));
    }
    $affected_rows = drush_db_delete('watchdog', $where['where'], $where['args']);
    drush_log(dt('!affected_rows watchdog messages have been deleted.', array('!affected_rows' => $affected_rows)), 'ok');
  }
}

/**
 * Build a WHERE snippet based on given parameters.
 *
 * @param $type
 *   String. Valid watchdog type.
 * @param $severity
 *   Int or String for a valid watchdog severity message.
 * @param $filter
 *   String. Value to filter watchdog messages by.
 * @param $criteria
 *   ('AND', 'OR'). Criteria for the WHERE snippet.
 * @return
 *   False or array with structure ('where' => string, 'args' => array())
 */
function core_watchdog_query($type = NULL, $severity = NULL, $filter = NULL, $criteria = 'AND') {
  $args = array();
  $conditions = array();
  if ($type) {
    $types = core_watchdog_message_types();
    if (array_search($type, $types) === FALSE) {
      $msg = "Unknown message type: !type.\nValid types are: !types.";
      return drush_set_error(dt($msg, array('!type' => $type, '!types' => implode(', ', $types))));
    }
    $conditions[] = "type = :type";
    $args[':type'] = $type;
  }
  if (!is_null($severity)) {
    $severities = core_watchdog_severity_levels();
    if (isset($severities[$severity])) {
      $level = $severity;
    }
    elseif (($key = array_search($severity, $severities)) !== FALSE) {
        $level = $key;
    }
    else {
      $level = FALSE;
    }
    if ($level === FALSE) {
      foreach ($severities as $key => $value) {
        $levels[] = "$value($key)";
      }
      $msg = "Unknown severity level: !severity.\nValid severity levels are: !levels.";
      return drush_set_error(dt($msg, array('!severity' => $severity, '!levels' => implode(', ', $levels))));
    }
    $conditions[] = 'severity = :severity';
    $args[':severity'] = $level;
  }
  if ($filter) {
    $conditions[] = "message LIKE :filter";
    $args[':filter'] = '%'.$filter.'%';
  }

  $where = implode(" $criteria ", $conditions);

  return array('where' => $where, 'args' => $args);
}

/**
 * Helper function to obtain the severity levels based on drupal version.
 *
 * This is a copy of watchdog_severity_levels() without t().
 *
 * Severity levels, as defined in RFC 3164: http://www.ietf.org/rfc/rfc3164.txt.
 *
 * @return
 *   Array of watchdog severity levels.
 */
function core_watchdog_severity_levels() {
  if (drush_drupal_major_version() == 5) {
    return array(
      WATCHDOG_NOTICE => 'notice',
      WATCHDOG_WARNING => 'warning',
      WATCHDOG_ERROR => 'error'
    );
  }
  else {
    return array(
      WATCHDOG_EMERG    => 'emergency',
      WATCHDOG_ALERT    => 'alert',
      WATCHDOG_CRITICAL => 'critical',
      WATCHDOG_ERROR    => 'error',
      WATCHDOG_WARNING  => 'warning',
      WATCHDOG_NOTICE   => 'notice',
      WATCHDOG_INFO     => 'info',
      WATCHDOG_DEBUG    => 'debug',
    );
  }
}

/**
 * Helper function to obtain the message types based on drupal version.
 *
 * @return
 *   Array of watchdog message types.
 */
function core_watchdog_message_types() {
  if (drush_drupal_major_version() == 5) {
    return _watchdog_get_message_types();
  }
  else {
    return _dblog_get_message_types();
  }
}
