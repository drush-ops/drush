<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;
use Drush\Drupal\DrupalUtil;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Output\OutputInterface;
use Drush\Boot\DrupalBootLevels;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class WatchdogCommands extends DrushCommands
{
    const SHOW = 'watchdog:show';
    const LIST = 'watchdog:list';
    const TAIL = 'watchdog:tail';
    const DELETE = 'watchdog:delete';
    const SHOW_ONE = 'watchdog:show-one';

    public function __construct(protected Connection $connection)
    {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('database')
        );

        return $commandHandler;
    }

    /**
     * Show watchdog messages.
     */
    #[CLI\Command(name: self::SHOW, aliases: ['wd-show', 'ws', 'watchdog-show'])]
    #[CLI\Argument(name: 'substring', description: 'A substring to look search in error messages.')]
    #[CLI\Option(name: 'count', description: 'The number of messages to show.')]
    #[CLI\Option(name: 'severity', description: 'Restrict to messages of a given severity level (numeric or string).')]
    #[CLI\Option(name: 'severity-min', description: 'Restrict to messages of a given severity level and higher.')]
    #[CLI\Option(name: 'type', description: 'Restrict to messages of a given type.')]
    #[CLI\Option(name: 'extended', description: 'Return extended information about each message.')]
    #[CLI\Usage(name: 'drush watchdog:show', description: 'Show a listing of most recent 10 messages.')]
    #[CLI\Usage(name: 'drush watchdog:show "cron run successful"', description: 'Show a listing of most recent 10 messages containing the string <info>cron run successful</info>.')]
    #[CLI\Usage(name: 'drush watchdog:show --count=46', description: 'Show a listing of most recent 46 messages.')]
    #[CLI\Usage(name: 'drush watchdog:show --severity=Notice', description: 'Show a listing of most recent 10 messages with a severity of notice.')]
    #[CLI\Usage(name: 'drush watchdog:show --severity-min=Warning', description: 'Show a listing of most recent 10 messages with a severity of warning or higher.')]
    #[CLI\Usage(name: 'drush watchdog:show --type=php', description: 'Show a listing of most recent 10 messages of type php')]
    #[CLI\FieldLabels(labels: [
        'wid' => 'ID',
        'type' => 'Type',
        'message' => 'Message',
        'severity' => 'Severity',
        'location' => 'Location',
        'hostname' => 'Hostname',
        'date' => 'Date',
        'username' => 'Username',
        'uid' => ' Uid',
    ])]
    #[CLI\ValidateModulesEnabled(modules: ['dblog'])]
    #[CLI\FilterDefaultField(field: 'message')]
    #[CLI\DefaultTableFields(fields: ['wid', 'date', 'type', 'severity', 'message'])]
    #[CLI\Complete(method_name_or_callable: 'watchdogComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function show($substring = '', $options = ['format' => 'table', 'count' => 10, 'severity' => self::REQ, 'severity-min' => self::REQ, 'type' => self::REQ, 'extended' => false]): ?RowsOfFields
    {
        $where = $this->where((string)$options['type'], $options['severity'], $substring, 'AND', $options['severity-min']);
        $query = $this->connection->select('watchdog', 'w')
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
            return null;
        } else {
            return new RowsOfFields($table);
        }
    }

    /**
     * Interactively filter the watchdog message listing.
     */
    #[CLI\Command(name: self::LIST, aliases: ['wd-list,watchdog-list'])]
    #[CLI\Argument(name: 'substring', description: 'A substring to look search in error messages.')]
    #[CLI\Option(name: 'count', description: 'The number of messages to show.')]
    #[CLI\Option(name: 'severity', description: 'Restrict to messages of a given severity level (numeric or string).')]
    #[CLI\Option(name: 'type', description: 'Restrict to messages of a given type.')]
    #[CLI\Option(name: 'extended', description: 'Return extended information about each message.')]
    #[CLI\Usage(name: 'drush watchdog:list', description: 'Prompt for message type or severity, then run watchdog:show.')]
    #[CLI\FieldLabels(labels: [
        'wid' => 'ID',
        'type' => 'Type',
        'message' => 'Message',
        'severity' => 'Severity',
        'location' => 'Location',
        'hostname' => 'Hostname',
        'date' => 'Date',
        'username' => 'Username',
    ])]
    #[CLI\ValidateModulesEnabled(modules: ['dblog'])]
    #[CLI\FilterDefaultField(field: 'message')]
    #[CLI\DefaultTableFields(fields: ['wid', 'date', 'type', 'severity', 'message'])]
    #[CLI\Complete(method_name_or_callable: 'watchdogComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function watchdogList($substring = '', $options = ['format' => 'table', 'count' => 10, 'extended' => false]): ?RowsOfFields
    {
        $options['severity-min'] = null;
        return $this->show($substring, $options);
    }

    /**
     * Tail watchdog messages.
     */
    #[CLI\Command(name: self::TAIL, aliases: ['wd-tail',  'wt', 'watchdog-tail'])]
    #[CLI\Argument(name: 'substring', description: 'A substring to look search in error messages.')]
    #[CLI\Option(name: 'severity', description: 'Restrict to messages of a given severity level (numeric or string).')]
    #[CLI\Option(name: 'severity-min', description: 'Restrict to messages of a given severity level and higher.')]
    #[CLI\Option(name: 'type', description: 'Restrict to messages of a given type.')]
    #[CLI\Option(name: 'extended', description: 'Return extended information about each message.')]
    #[CLI\Usage(name: 'drush watchdog:tail', description: 'Continuously tail watchdog messages.')]
    #[CLI\Usage(name: 'drush watchdog:tail "cron run successful"', description: 'Continuously tail watchdog messages, filtering on the string <info>cron run successful</info>.')]
    #[CLI\Usage(name: 'drush watchdog:tail --severity=Notice', description: 'Continuously tail watchdog messages, filtering severity of notice.')]
    #[CLI\Usage(name: 'drush watchdog:tail --severity-min=Warning', description: 'Continuously tail watchdog messages, filtering for a severity of warning or higher.')]
    #[CLI\Usage(name: 'drush watchdog:tail --type=php', description: 'Continuously tail watchdog messages, filtering on type equals php.')]
    #[CLI\ValidateModulesEnabled(modules: ['dblog'])]
    #[CLI\Version(version: '10.6')]
    #[CLI\Complete(method_name_or_callable: 'watchdogComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function tail(OutputInterface $output, $substring = '', $options = ['severity' => self::REQ, 'severity-min' => self::REQ, 'type' => self::REQ, 'extended' => false]): void
    {
        $where = $this->where($options['type'], $options['severity'], $substring, 'AND', $options['severity-min']);
        if (empty($where['where'])) {
            $where = [
              'where' => 'wid > :wid',
              'args' => [],
            ];
        } else {
            $where['where'] .= " AND wid > :wid";
        }

        $last_seen_wid = 0;
        while (true) {
            $where['args'][':wid'] = $last_seen_wid;
            $query = $this->connection->select('watchdog', 'w')
                ->fields('w')
                ->orderBy('wid', 'DESC');
            if ($last_seen_wid === 0) {
                $query->range(0, 10);
            }
            $query->where($where['where'], $where['args']);

            $rsc = $query->execute();
            while ($result = $rsc->fetchObject()) {
                if ($result->wid > $last_seen_wid) {
                    $last_seen_wid = $result->wid;
                }
                $row = $this->formatResult($result, $options['extended']);
                $msg = "$row->wid\t$row->date\t$row->type\t$row->severity\t$row->message";
                $output->writeln($msg);
            }
            sleep(2);
        }
    }

    #[CLI\Hook(type: HookManager::INTERACT, target: self::LIST)]
    public function interactList($input, $output): void
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
     */
    #[CLI\Command(name: self::DELETE, aliases: ['wd-del', 'wd-delete', 'wd', 'watchdog-delete'])]
    #[CLI\Argument(name: 'substring', description: 'Delete all log records with this text in the messages.')]
    #[CLI\Option(name: 'severity', description: 'Delete messages of a given severity level.')]
    #[CLI\Option(name: 'type', description: 'Delete messages of a given type.')]
    #[CLI\Usage(name: 'drush watchdog:delete', description: 'Delete all messages.')]
    #[CLI\Usage(name: 'drush watchdog:delete 64', description: 'Delete messages with id 64.')]
    #[CLI\Usage(name: 'drush watchdog:delete "cron run succesful"', description: 'Delete messages containing the string "cron run succesful".')]
    #[CLI\Usage(name: '@usage drush watchdog:delete --severity=Notice', description: 'Delete all messages with a severity of notice.')]
    #[CLI\Usage(name: 'drush watchdog:delete --type=cron', description: 'Delete all messages of type cron.')]
    #[CLI\ValidateModulesEnabled(modules: ['dblog'])]
    #[CLI\Complete(method_name_or_callable: 'watchdogComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function delete($substring = '', $options = ['severity' => self::REQ, 'type' => self::REQ]): void
    {
        if ($substring == 'all') {
            $this->output()->writeln(dt('All watchdog messages will be deleted.'));
            if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
                throw new UserAbortException();
            }
            $ret = $this->connection->truncate('watchdog')->execute();
            $this->logger()->success(dt('All watchdog messages have been deleted.'));
        } elseif (is_numeric($substring)) {
            $this->output()->writeln(dt('Watchdog message #!wid will be deleted.', ['!wid' => $substring]));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
            $affected_rows = $this->connection->delete('watchdog')->condition('wid', $substring)->execute();
            if ($affected_rows == 1) {
                $this->logger()->success(dt('Watchdog message #!wid has been deleted.', ['!wid' => $substring]));
            } else {
                throw new \Exception(dt('Watchdog message #!wid does not exist.', ['!wid' => $substring]));
            }
        } else {
            if ((empty($substring)) && (!isset($options['type'])) && (!isset($options['severity']))) {
                throw new \Exception(dt('No options provided.'));
            }
            $where = $this->where($options['type'], $options['severity'], $substring, 'OR');
            $this->output()->writeln(dt('All messages with !where will be deleted.', ['!where' => preg_replace("/message LIKE %$substring%/", "message body containing '$substring'", strtr($where['where'], $where['args']))]));
            if (!$this->io()->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }
            $affected_rows = $this->connection->delete('watchdog')
                ->where($where['where'], $where['args'])
                ->execute();
            $this->logger()->success(dt('!affected_rows watchdog messages have been deleted.', ['!affected_rows' => $affected_rows]));
        }
    }

    /**
     * Show one log record by ID.
     */
    #[CLI\Command(name: self::SHOW_ONE, aliases: ['wd-one', 'watchdog-show-one'])]
    #[CLI\Argument(name: 'id', description: 'Watchdog Id')]
    #[CLI\ValidateModulesEnabled(modules: ['dblog'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function showOne($id, $options = ['format' => 'yaml']): PropertyList
    {
        $rsc = $this->connection->select('watchdog', 'w')
            ->fields('w')
            ->condition('wid', (int)$id)
            ->range(0, 1)
            ->execute();
        $result = $rsc->fetchObject();
        if (!$result) {
            throw new \Exception(dt('Watchdog message #!wid not found.', ['!wid' => $id]));
        }
        return new PropertyList($this->formatResult($result, true));
    }

    /**
     * Build a WHERE snippet based on given parameters.
     *
     * Example: ('where' => string, 'args' => [])
     *
     * @param $type
     *   String. Valid watchdog type.
     * @param $severity
     *   Int or String for a valid watchdog severity message.
     * @param $filter
     *   String. Value to filter watchdog messages by.
     * @param $criteria
     *   ('AND', 'OR'). Criteria for the WHERE snippet.
     * @param $severity_min
     *   Int or String for the minimum severity to return.
     */
    protected function where(?string $type = null, $severity = null, ?string $filter = null, string $criteria = 'AND', int|string $severity_min = null): array
    {
        $args = [];
        $conditions = [];
        if ($type) {
            $types = $this->messageTypes();
            if (!in_array($type, $types)) {
                $msg = "Unrecognized message type: !type.\nRecognized types are: !types.";
                throw new \Exception(dt($msg, ['!type' => $type, '!types' => implode(', ', $types)]));
            }
            $conditions[] = "type = :type";
            $args[':type'] = $type;
        }
        if (!empty($severity) && !empty($severity_min)) {
            $msg = "--severity=!severity  --severity-min=!severity_min\nYou may provide a value for one of these parameters but not both.";
            throw new \Exception(dt($msg, ['!severity' => $severity, '!severity_min' => $severity_min]));
        }
        // From here we know that only one of --severity or --severity-min might
        // have a value but not both of them.
        if (!empty($severity) || !empty($severity_min)) {
            if (empty($severity)) {
                $severity = $severity_min;
                $operator = '<=';
            } else {
                $operator = '=';
            }
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
                $msg = "Unknown severity level: !severity\nValid severity levels are: !levels.";
                throw new \Exception(dt($msg, ['!severity' => $severity, '!levels' => implode(', ', $levels)]));
            }
            $conditions[] = "severity $operator :severity";
            $args[':severity'] = $level;
        }
        if ($filter) {
            $conditions[] = "message LIKE :filter";
            $args[':filter'] = '%' . $filter . '%';
        }

        $where = implode(" $criteria ", $conditions);

        return ['where' => $where, 'args' => $args];
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
    protected function formatResult($result, bool $extended = false)
    {
        // Severity.
        $severities = RfcLogLevel::getLevels();
        $result->severity = trim(DrupalUtil::drushRender($severities[$result->severity]));

        // Date.
        $result->date = date('d/M H:i', (int)$result->timestamp);
        unset($result->timestamp);

        // Username.
        $result->username = (new AnonymousUserSession())->getAccountName() ?: dt('Anonymous');
        $account = User::load($result->uid);
        if ($account && !$account->isAnonymous()) {
            $result->username = $account->getAccountName();
        }

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
            $message_length = PHP_INT_MAX;
        }
        $result->message = Unicode::truncate(strip_tags(Html::decodeEntities($result->message)), $message_length);

        return $result;
    }

    /**
     * Helper function to obtain the message types based on drupal version.
     *
     * @return array
     *   Array of watchdog message types.
     */
    public static function messageTypes(): array
    {
        return _dblog_get_message_types();
    }

    public function watchdogComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('severity') || $input->mustSuggestOptionValuesFor('severity-min')) {
            $suggestions->suggestValues(RfcLogLevel::getLevels());
        }
        if ($input->mustSuggestOptionValuesFor('type')) {
            $suggestions->suggestValues(self::messageTypes());
        }
    }
}
