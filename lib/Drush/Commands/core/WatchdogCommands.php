<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;

class WatchdogCommands extends DrushCommands {

  /**
   * Show watchdog messages.
   *
   * @command watchdog-show
   * @drupal-dependencies dblog
   * @param $substring A substring to look search in error messages.
   * @option count The number of messages to show. Defaults to 10.
   * @option severity Restrict to messages of a given severity level.
   * @option type Restrict to messages of a given type.
   * @option extended Return extended information about each message.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @usage  drush watchdog-show
   *   Show a listing of most recent 10 messages.
   * @usage drush watchdog-show "cron run succesful"
   *   Show a listing of most recent 10 messages containing the string "cron run succesful".
   * @usage drush watchdog-show --count=46
   *   Show a listing of most recent 46 messages.
   * @usage drush watchdog-show --severity=notice
   *   Show a listing of most recent 10 messages with a severity of notice.
   * @usage drush watchdog-show --type=php
   *   Show a listing of most recent 10 messages of type php
   * @aliases wd-show,ws
   * @field-labels
   *   wid: ID
   *   type: Type
   *   message: Message
   *   severity: Severity
   *   location: Location
   *   hostname: Hostname
   *   date: Date
   *   username: Username
   * @default-fields wid,date,type,severity,message
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  function show($substring = '', $options = ['format' => 'table', 'count' => 10, 'severity' => NULL, 'type' => NULL, 'extended' => FALSE]) {
    $where = $this->where($options['type'], $options['severity'], $substring);
    $rsc = drush_db_select('watchdog', '*', $where['where'], $where['args'], 0, $options['count'], 'wid', 'DESC');
    $table = array();
    while ($result = drush_db_fetch_object($rsc)) {
      $row = $this->formatResult($result, $options['extended']);
      $table[$row->wid] = (array)$row;
    }
    if (empty($table)) {
      $this->logger()->info(dt('No log messages available.'));
    }
    else {
      return new RowsOfFields($table);
    }
  }

  /**
   * Show watchdog messages.
   *
   * @command watchdog-list
   * @drupal-dependencies dblog
   * @param $substring A substring to look search in error messages.
   * @option count The number of messages to show. Defaults to 10.
   * @option extended Return extended information about each message.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @usage  drush watchdog-list
   *   Prompt for message type or severity, then run watchdog-show.
   * @aliases wd-list
   * @field-labels
   *   wid: ID
   *   type: Type
   *   message: Message
   *   severity: Severity
   *   location: Location
   *   hostname: Hostname
   *   date: Date
   *   username: Username
   * @default-fields wid,date,type,severity,message
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  function watchdogList($substring = '', $options = ['format' => 'table', 'count' => 10, 'extended' => FALSE]) {
    return $this->show($substring, $options);
  }

  /**
   * @hook interact watchdog-list
   */
  public function interactShow($input, $output) {
    drush_include_engine('drupal', 'environment');

    $choices['-- types --'] = dt('== message types ==');
    $types = drush_watchdog_message_types();
    foreach ($types as $key => $type) {
      $choices[$key] = $type;
    }
    $choices['-- levels --'] = dt('== severity levels ==');
    $severities = drush_watchdog_severity_levels();
    foreach ($severities as $key => $value) {
      $choices[$key] = "$value($key)";
    }
    $option = drush_choice($choices, dt('Select a message type or severity level.'));
    if ($option === FALSE) {
      // TODO: We need to throw an exception to abort from an interact hook.
      // Need to define an abort type and catch it.
      return drush_user_abort();
    }
    if (isset($types[$option])) {
      $input->setOption('type', $types[$option]);
    }
    else {
      $input->setOption('severity', $severities[$option]);
    }
  }

  /**
   * Delete watchdog log records.
   *
   * @command watchdog-delete
   * @param $substring Delete all log records with this text in the messages.
   * @option severity Delete messages of a given severity level.
   * @option type Delete messages of a given type.
   * @drupal-dependencies dblog
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @usage drush watchdog-delete all
   *   Delete all messages.
   * @usage drush watchdog-delete 64
   *   Delete messages with id 64.
   * @usage drush watchdog-delete "cron run succesful"
   *   Delete messages containing the string "cron run succesful".
   * @usage drush watchdog-delete --severity=notice
   *   Delete all messages with a severity of notice.
   * @usage drush watchdog-delete --type=cron
   *   Delete all messages of type cron.
   * @aliases wd-del,wd-delete,wd
   * @return void
   */
  public function delete($substring = '', $options = ['severity' => NULL, 'type' => NULL]) {
    drush_include_engine('drupal', 'environment');

    if ($substring == 'all') {
      drush_print(dt('All watchdog messages will be deleted.'));
      if (!drush_confirm(dt('Do you really want to continue?'))) {
        return drush_user_abort();
      }
      drush_db_delete('watchdog');
      $this->logger()->success(dt('All watchdog messages have been deleted.'));
    }
    else if (is_numeric($substring)) {
      drush_print(dt('Watchdog message #!wid will be deleted.', array('!wid' => $substring)));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        return drush_user_abort();
      }
      $affected_rows = drush_db_delete('watchdog', 'wid=:wid', array(':wid' => $substring));
      if ($affected_rows == 1) {
        $this->logger->success(log(dt('Watchdog message #!wid has been deleted.', array('!wid' => $substring))));
      }
      else {
        throw new \Exception(dt('Watchdog message #!wid does not exist.', array('!wid' => $substring)));
      }
    }
    else {

      if ((!isset($substring))&&(!isset($options['type']))&&(!isset($options['severity']))) {
        throw new \Exception(dt('No options provided.'));
      }
      $where = $this->where($options['type'], $options['severity'], $substring, 'OR');
      drush_print(dt('All messages with !where will be deleted.', array('!where' => preg_replace("/message LIKE %$substring%/", "message body containing '$substring'" , strtr($where['where'], $where['args'])))));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        return drush_user_abort();
      }
      $affected_rows = drush_db_delete('watchdog', $where['where'], $where['args']);
      $this->logger()->success(dt('!affected_rows watchdog messages have been deleted.', array('!affected_rows' => $affected_rows)));
    }
  }

  /**
   * Show one log record by ID.
   *
   * @command watchdog-show-one
   * @param $id Watchdog Id
   * @drupal-dependencies dblog
   * @aliases wd-one
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function showOne($id, $options = ['format' => 'yaml']) {
    $rsc = drush_db_select('watchdog', '*', 'wid = :wid', array(':wid' => (int)$id), 0, 1);
    $result = drush_db_fetch_object($rsc);
    if (!$result) {
      throw new \Exception(dt('Watchdog message #!wid not found.', array('!wid' => $id)));
    }
    $result = core_watchdog_format_result($result, TRUE);
    return $result;
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
   *   An array with structure ('where' => string, 'args' => array())
   */
  protected function where($type = NULL, $severity = NULL, $filter = NULL, $criteria = 'AND') {
    $args = array();
    $conditions = array();
    if ($type) {
      $types = drush_watchdog_message_types();
      if (array_search($type, $types) === FALSE) {
        $msg = "Unrecognized message type: !type.\nRecognized types are: !types.";
        throw new \Exception(dt($msg, array('!type' => $type, '!types' => implode(', ', $types))));
      }
      $conditions[] = "type = :type";
      $args[':type'] = $type;
    }
    if (isset($severity)) {
      $severities = drush_watchdog_severity_levels();
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
        throw new \Exception(dt($msg, array('!severity' => $severity, '!levels' => implode(', ', $levels))));
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
   * Format a watchdog database row.
   *
   * @param $result
   *   Array. A database result object.
   * @param $extended
   *   Boolean. Return extended message details.
   * @return
   *   Array. The result object with some attributes themed.
   */
  function formatResult($result, $extended = FALSE) {
    // Severity.
    $severities = drush_watchdog_severity_levels();
    $result->severity = $severities[$result->severity];

    // Date.
    $result->date = format_date($result->timestamp, 'custom', 'd/M H:i');
    unset($result->timestamp);

    // Message.
    $variables = $result->variables;
    if (is_string($variables)) {
      $variables = unserialize($variables);
    }
    if (is_array($variables)) {
      $result->message = strtr($result->message, $variables);
    }
    unset($result->variables);
    $message_length = 188;

    // Print all the data available
    if ($extended) {
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
      $message_length = PHP_INT_MAX;
    }

    if (drush_drupal_major_version() >= 8) {
      $result->message = Unicode::truncate(strip_tags(Html::decodeEntities($result->message)), $message_length, FALSE, FALSE);
    }
    else {
      $result->message = truncate_utf8(strip_tags(decode_entities($result->message)), $message_length, FALSE, FALSE);
    }

    return $result;
  }
}
