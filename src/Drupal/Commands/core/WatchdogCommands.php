<?php
namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Database;
use Drupal\Core\Logger\RfcLogLevel;
use Drush\Commands\DrushCommands;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Drush\Exceptions\UserAbortException;

class WatchdogCommands extends DrushCommands
{

    /**
     * Show watchdog messages.
     *
     * @command watchdog:show
     * @drupal-dependencies dblog
     * @param $substring A substring to look search in error messages.
     * @option count The number of messages to show. Defaults to 10.
     * @option severity Restrict to messages of a given severity level.
     * @option type Restrict to messages of a given type.
     * @option extended Return extended information about each message.
     * @usage  drush watchdog-show
     *   Show a listing of most recent 10 messages.
     * @usage drush watchdog:show "cron run succesful"
     *   Show a listing of most recent 10 messages containing the string "cron run succesful".
     * @usage drush watchdog:show --count=46
     *   Show a listing of most recent 46 messages.
     * @usage drush watchdog:show --severity=Notice
     *   Show a listing of most recent 10 messages with a severity of notice.
     * @usage drush watchdog:show --type=php
     *   Show a listing of most recent 10 messages of type php
     * @aliases wd-show,ws,watchdog-show
     * @validate-module-enabled dblog
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
    public function show($substring = '', $options = ['format' => 'table', 'count' => 10, 'severity' => self::REQ, 'type' => self::REQ, 'extended' => false])
    {
        $where = $this->where($options['type'], $options['severity'], $substring);
        $query = Database::getConnection()->select('watchdog', 'w')
            ->range(0, $options['count'])
            ->fields('w')
            ->orderBy('wid', 'DESC');
        if (!empty($where['where'])) {
            $query->where($where['where'], $where['args']);
        }
        $rsc = $query->execute();
        while ($result = $rsc->fetchObject()) {
            $row = $this->formatResult($result, $options['extended']);
            $table[$row->wid] = (array)$row;
        }
        if (empty($table)) {
            $this->logger()->notice(dt('No log messages available.'));
        } else {
            return new RowsOfFields($table);
        }
    }

    /**
     * Interactively filter the watchdog message listing.
     *
     * @command watchdog:list
     * @drupal-dependencies dblog
     * @param $substring A substring to look search in error messages.
     * @option count The number of messages to show. Defaults to 10.
     * @option extended Return extended information about each message.
     * @option severity Restrict to messages of a given severity level.
     * @option type Restrict to messages of a given type.
     * @usage  drush watchdog-list
     *   Prompt for message type or severity, then run watchdog-show.
     * @aliases wd-list,watchdog-list
     * @hidden-options type,severity
     * @validate-module-enabled dblog
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
    public function watchdogList($substring = '', $options = ['format' => 'table', 'count' => 10, 'extended' => false])
    {
        return $this->show($substring, $options);
    }

    /**
     * @hook interact watchdog-list
     * @throws \Drush\Exceptions\UserAbortException
     */
    public function interactList($input, $output)
    {

        $choices['-- types --'] = dt('== message types ==');
        $types = $this->messageTypes();
        foreach ($types as $key => $type) {
            $choices[$key] = $type;
        }
        $choices['-- levels --'] = dt('== severity levels ==');
        $severities = RfcLogLevel::getLevels();

        foreach ($severities as $key => $value) {
            $choices[$key] = $value;
        }
        $option = $this->io()->choice(dt('Select a message type or severity level'), $choices);
        if (isset($types[$option])) {
            $input->setOption('type', $types[$option]);
        } else {
            $input->setOption('severity', $option);
        }
    }

    /**
     * Delete watchdog log records.
     *
     * @command watchdog:delete
     * @param $substring Delete all log records with this text in the messages.
     * @option severity Delete messages of a given severity level.
     * @option type Delete messages of a given type.
     * @drupal-dependencies dblog
     * @usage drush watchdog:delete all
     *   Delete all messages.
     * @usage drush watchdog:delete 64
     *   Delete messages with id 64.
     * @usage drush watchdog:delete "cron run succesful"
     *   Delete messages containing the string "cron run succesful".
     * @usage drush watchdog:delete --severity=notice
     *   Delete all messages with a severity of notice.
     * @usage drush watchdog:delete --type=cron
     *   Delete all messages of type cron.
     * @aliases wd-del,wd-delete,wd,watchdog-delete
     * @validate-module-enabled dblog
     * @return void
     */
    public function delete($substring = '', $options = ['severity' => self::REQ, 'type' => self::REQ])
    {
        if ($substring == 'all') {
            $this->output()->writeln(dt('All watchdog messages will be deleted.'));
            if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
                throw new UserAbortException();
            }
            $ret = Database::getConnection()->truncate('watchdog')->execute();
            $this->logger()->success(dt('All watchdog messages have been deleted.'));
        } else if (is_numeric($substring)) {
            $this->output()->writeln(dt('Watchdog message #!wid will be deleted.', array('!wid' => $substring)));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
            $affected_rows = Database::getConnection()->delete('watchdog')->condition('wid', $substring)->execute();
            if ($affected_rows == 1) {
                $this->logger()->success(dt('Watchdog message #!wid has been deleted.', array('!wid' => $substring)));
            } else {
                throw new \Exception(dt('Watchdog message #!wid does not exist.', array('!wid' => $substring)));
            }
        } else {
            if ((!isset($substring))&&(!isset($options['type']))&&(!isset($options['severity']))) {
                throw new \Exception(dt('No options provided.'));
            }
            $where = $this->where($options['type'], $options['severity'], $substring, 'OR');
            $this->output()->writeln(dt('All messages with !where will be deleted.', array('!where' => preg_replace("/message LIKE %$substring%/", "message body containing '$substring'", strtr($where['where'], $where['args'])))));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
            $affected_rows = Database::getConnection()->delete('watchdog')
                ->where($where['where'], $where['args'])
                ->execute();
            $this->logger()->success(dt('!affected_rows watchdog messages have been deleted.', array('!affected_rows' => $affected_rows)));
        }
    }

    /**
     * Show one log record by ID.
     *
     * @command watchdog:show-one
     * @param $id Watchdog Id
     * @drupal-dependencies dblog
     * @aliases wd-one,watchdog-show-one
     * @validate-module-enabled dblog
     *
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     */
    public function showOne($id, $options = ['format' => 'yaml'])
    {
        $rsc = Database::getConnection()->select('watchdog', 'w')
            ->fields('w')
            ->condition('wid', (int)$id)
            ->range(0, 1)
            ->execute();
        $result = $rsc->fetchObject();
        if (!$result) {
            throw new \Exception(dt('Watchdog message #!wid not found.', array('!wid' => $id)));
        }
        return new PropertyList($this->formatResult($result));
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
    protected function where($type = null, $severity = null, $filter = null, $criteria = 'AND')
    {
        $args = array();
        $conditions = array();
        if ($type) {
            $types = $this->messageTypes();
            if (array_search($type, $types) === false) {
                $msg = "Unrecognized message type: !type.\nRecognized types are: !types.";
                throw new \Exception(dt($msg, array('!type' => $type, '!types' => implode(', ', $types))));
            }
            $conditions[] = "type = :type";
            $args[':type'] = $type;
        }
        if (isset($severity)) {
            $severities = RfcLogLevel::getLevels();
            if (isset($severities[$severity])) {
                $level = $severity;
            } elseif (($key = array_search($severity, $severities)) !== false) {
                $level = $key;
            } else {
                $level = false;
            }
            if ($level === false) {
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
    protected function formatResult($result, $extended = false)
    {
        // Severity.
        $severities = RfcLogLevel::getLevels();
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
            } else {
                $result->username = dt('Anonymous');
            }
            unset($result->uid);
            $message_length = PHP_INT_MAX;
        }
        $result->message = Unicode::truncate(strip_tags(Html::decodeEntities($result->message)), $message_length, false, false);

        return $result;
    }

    /**
     * Helper function to obtain the message types based on drupal version.
     *
     * @return
     *   Array of watchdog message types.
     */
    public static function messageTypes()
    {
        return _dblog_get_message_types();
    }
}
