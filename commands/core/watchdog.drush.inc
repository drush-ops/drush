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
      'count' => 'The number of messages to show. Defaults to 10.',
      'severity' => 'Restrict to messages of a given severity level.',
      'type' => 'Restrict to messages of a given type.',
      'tail' => 'Continuously show new watchdog messages until interrupted.',
      'sleep-delay' => 'To be used in conjunction with --tail. This is the number of seconds to wait between each poll to the database. Delay is 1 second by default.'
    ),
    'examples' => array(
      'drush watchdog-show' => 'Show a listing of most recent 10 messages.',
      'drush watchdog-show 64' => 'Show in detail message with id 64.',
      'drush watchdog-show "cron run succesful"' => 'Show a listing of most recent 10 messages containing the string "cron run succesful".',
      'drush watchdog-show --count=46' => 'Show a listing of most recent 46 messages.',
      'drush watchdog-show --severity=notice' => 'Show a listing of most recent 10 messages with a severity of notice.',
      'drush watchdog-show --type=php' => 'Show a listing of most recent 10 messages of type php.',
      'drush watchdog-show --tail' => 'Show a listing of most recent 10 messages and continue showing messages as they are registered in the watchdog.',
      'drush watchdog-show --tail --sleep-delay=2' => 'Do a tail of the watchdog with a delay of two seconds between each poll to the database.',
    ),
    'aliases' => array('wd-show', 'ws'),
  );
  $items['watchdog-delete'] = array(
    'description' => 'Delete watchdog messages.',
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'arguments' => array(
      'severity' => 'Delete messages of a given severity level.',
      'type' => 'Delete messages of a given type.',
    ),
    'examples' => array(
      'drush watchdog-delete all' => 'Delete all messages.',
      'drush watchdog-delete 64' => 'Delete messages with id 64.',
      'drush watchdog-delete "cron run succesful"' => 'Delete messages containing the string "cron run succesful".',
      'drush watchdog-delete --severity=notice' => 'Delete all messages with a severity of notice.',
      'drush watchdog-delete --type=cron' => 'Delete all messages of type cron.',
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
  drush_include_engine('drupal', 'environment');
  $severities = core_watchdog_severity_levels();
  foreach ($severities as $key => $value) {
    $options[] = "$value($key)";
  }
  $option = drush_choice($options, dt('Select a message type or severity level.'));
  if ($option === FALSE) {
    return drush_user_abort();
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
  $result = core_watchdog_format_result($result, TRUE);
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
  $tail = drush_get_option('tail', FALSE);

  $where = core_watchdog_query($type, $severity, $filter);
  if ($where === FALSE) {
    return drush_log(dt('Aborting.'));
  }
  $rsc = drush_db_select('watchdog', '*', $where['where'], $where['args'], 0, $count, 'wid', 'DESC');
  if ($rsc === FALSE) {
    return drush_log(dt('Aborting.'));
  }
  $header = array(dt('Id'), dt('Date'), dt('Severity'), dt('Type'), dt('Message'));
  while ($result = drush_db_fetch_object($rsc)) {
    $row = core_watchdog_format_result($result);
    $table[] = array($row->wid, $row->date, $row->severity, $row->type, $row->message);
  }
  if (empty($table)) {
    return drush_log(dt('No log messages available.'), 'ok');
  }
  else {
    drush_log(dt('Most recent !count watchdog log messages:', array('!count' => $count)));
    if ($tail) {
      $table = array_reverse($table);
    }
    array_unshift($table, $header);
    $tbl = drush_print_table($table, TRUE);
  }

  if ($tail) {
    // We will reuse the table object to display each line generated while in tail mode.
    // To make it possible some hacking is done on the object: remove the header and reset the rows on each iteration.
    $tbl->_headers = NULL;
    // Obtain the last wid.
    $last = array_pop($table);
    $last_wid = $last[0];
    // Adapt the where snippet.
    if ($where['where'] != '') {
      $where['where'] .= ' AND ';
    }
    $where['where'] .= 'wid > :wid';
    // sleep-delay
    $sleep_delay = drush_get_option('sleep-delay', 1);
    while (TRUE) {
      $where['args'][':wid'] = $last_wid;
      $table = array();
      // Reset table rows.
      $tbl->_data = array();
      $rsc = drush_db_select('watchdog', '*', $where['where'], $where['args'], NULL, NULL, 'wid', 'ASC');
      while ($result = drush_db_fetch_object($rsc)) {
        $row = core_watchdog_format_result($result);
        $table[] = array($row->wid, $row->date, $row->severity, $row->type, $row->message);
        #$tbl->addRow(array($row->wid, $row->date, $row->severity, $row->type, $row->message));
        $last_wid = $row->wid;
      }
      $tbl->addData($table);
      print $tbl->_buildTable();
      sleep($sleep_delay);
    }
  }
  else {
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
function core_watchdog_format_result($result, $full = FALSE) {
  // Severity.
  drush_include_engine('drupal', 'environment');
  $severities = core_watchdog_severity_levels();
  $result->severity = $severities[$result->severity];

  // Date.
  $result->date = format_date($result->timestamp, 'custom', 'd/M H:i');
  unset($result->timestamp);

  // Message.
  if (drush_drupal_major_version() >= 6) {
    $variables = $result->variables;
    if (is_string($variables)) {
      $variables = unserialize($variables);
    }
    if (is_array($variables)) {
      $result->message = strtr($result->message, $variables);
    }
    unset($result->variables);
  }
  $result->message = truncate_utf8(strip_tags(decode_entities($result->message)), 188, FALSE, FALSE);

  // This data is only used in a detailed view.
  if ($full) {
    // Possible empty values.
    if (empty($result->link)) {
      unset($result->link);
    }
    if (empty($result->referer)) {
      unset($result->referer);
    }
    // Username.
    if ($account = user_load($result->uid)) {
      $result->username = $account->name;
    }
    else {
      $result->username = dt('Anonymous');
    }
    unset($result->uid);
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
      return drush_user_abort();
    }
    drush_db_delete('watchdog');
    drush_log(dt('All watchdog messages have been deleted.'), 'ok');
  }
  else if (is_numeric($arg)) {
    drush_print(dt('Watchdog message #!wid will be deleted.', array('!wid' => $arg)));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
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
    $where = core_watchdog_query($type, $severity, $arg, 'OR');
    if ($where === FALSE) {
      // Drush set error was already called by core_watchdog_query
      return FALSE;
    }
    drush_print(dt('All messages with !where will be deleted.', array('!where' => preg_replace("/message LIKE %$arg%/", "message body containing '$arg'" , strtr($where['where'], $where['args'])))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
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
      $msg = "Unrecognized message type: !type.\nRecognized types are: !types.";
      return drush_set_error('WATCHDOG_UNRECOGNIZED_TYPE', dt($msg, array('!type' => $type, '!types' => implode(', ', $types))));
    }
    $conditions[] = "type = :type";
    $args[':type'] = $type;
  }
  if (!is_null($severity)) {
    drush_include_engine('drupal', 'environment');
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
 * Helper function to obtain the message types based on drupal version.
 *
 * @return
 *   Array of watchdog message types.
 */
function core_watchdog_message_types() {
  return _dblog_get_message_types();
}
