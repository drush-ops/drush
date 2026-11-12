<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drush\Commands\OutputFormat;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Helpers shared by the watchdog commands.
 *
 * Classes using this trait inject Connection as $this->connection and
 * ModuleHandlerInterface as $this->moduleHandler. Those that format records
 * also inject EntityTypeManagerInterface as $this->entityTypeManager.
 */
trait WatchdogTrait
{
    use StringTranslationTrait;

    protected Connection $connection;
    protected ModuleHandlerInterface $moduleHandler;
    protected EntityTypeManagerInterface $entityTypeManager;

    /**
     * Human-readable labels of every field a formatted record may carry.
     */
    const array LABELS = [
        'wid' => 'ID',
        'date' => 'Date',
        'type' => 'Type',
        'severity' => 'Severity',
        'message' => 'Message',
        'username' => 'Username',
        'uid' => 'Uid',
        'location' => 'Location',
        'referer' => 'Referer',
        'hostname' => 'Hostname',
        'link' => 'Link',
    ];

    /**
     * Fields the listing table shows unless --fields says otherwise.
     */
    const array DEFAULT_FIELDS = ['wid', 'date', 'type', 'severity', 'message'];

    /**
     * Throws when the dblog module, which owns the watchdog table, is not installed.
     */
    protected function requireDblog(): void
    {
        if (!$this->moduleHandler->moduleExists('dblog')) {
            throw new \InvalidArgumentException((string) $this->t('The dblog module is not installed.'));
        }
    }

    /**
     * Build a WHERE snippet based on given parameters.
     *
     * @param $type
     *   A valid watchdog type.
     * @param $severity
     *   Int or string for a valid watchdog severity level.
     * @param $filter
     *   Value to filter watchdog messages by.
     * @param $criteria
     *   ('AND', 'OR'). Criteria for the WHERE snippet.
     * @param $severity_min
     *   Int or string for the minimum severity to return.
     *
     * @return array{where: string, args: array}
     */
    protected function where(?string $type = null, int|string|null $severity = null, ?string $filter = null, string $criteria = 'AND', int|string|null $severity_min = null): array
    {
        $args = $conditions = [];
        if ($type) {
            $types = $this->messageTypes();
            if (!in_array($type, $types)) {
                throw new \InvalidArgumentException((string) $this->t("Unrecognized message type: @type.\nRecognized types are: @types.", [
                    '@type' => $type,
                    '@types' => implode(', ', $types),
                ]));
            }
            $conditions[] = "type = :type";
            $args[':type'] = $type;
        }
        if (!empty($severity) && !empty($severity_min)) {
            throw new \InvalidArgumentException((string) $this->t("--severity=@severity  --severity-min=@min\nYou may provide a value for one of these parameters but not both.", [
                '@severity' => $severity,
                '@min' => $severity_min,
            ]));
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
            $conditions[] = "severity $operator :severity";
            $args[':severity'] = $this->severityLevel($severity);
        }
        if ($filter) {
            $conditions[] = "message LIKE :filter";
            $args[':filter'] = '%' . $filter . '%';
        }

        return ['where' => implode(" $criteria ", $conditions), 'args' => $args];
    }

    /**
     * Resolves a severity given as a number or a label to its RFC level.
     */
    protected function severityLevel(int|string $severity): int
    {
        $labels = $this->severityLabels();
        if (isset($labels[$severity])) {
            return (int) $severity;
        }
        if (($level = array_search($severity, $labels, true)) !== false) {
            return $level;
        }
        $levels = [];
        foreach ($labels as $level => $label) {
            $levels[] = "$label($level)";
        }
        throw new \InvalidArgumentException((string) $this->t("Unknown severity level: @severity\nValid severity levels are: @levels.\nEither use the default language levels, or use a number.", [
            '@severity' => $severity,
            '@levels' => implode(', ', $levels),
        ]));
    }

    /**
     * Severity labels keyed by RFC level.
     *
     * @return string[]
     */
    protected function severityLabels(): array
    {
        return array_map(fn($label) => (string) $label, RfcLogLevel::getLevels());
    }

    /**
     * Fetches the most recent matching records, formatted and keyed by wid.
     *
     * @param array{where: string, args: array} $where
     *   As returned by where().
     */
    protected function fetchRecords(array $where, int $count, bool $extended): array
    {
        $query = $this->connection->select('watchdog', 'w')
            ->range(0, $count)
            ->fields('w')
            ->orderBy('wid', 'DESC');
        if (!empty($where['where'])) {
            $query->where($where['where'], $where['args']);
        }
        $records = [];
        foreach ($query->execute() as $result) {
            $record = $this->formatResult($result, $extended);
            $records[$record['wid']] = $record;
        }
        return $records;
    }

    /**
     * Loads one record by id with all its details, or throws when it is missing.
     */
    protected function loadRecord(int $id): array
    {
        $result = $this->connection->select('watchdog', 'w')
            ->fields('w')
            ->condition('wid', $id)
            ->range(0, 1)
            ->execute()
            ->fetchObject();
        if (!$result) {
            throw new \InvalidArgumentException((string) $this->t('Watchdog message #@id not found.', ['@id' => $id]));
        }
        return $this->formatResult($result, true);
    }

    /**
     * Format a watchdog database row.
     *
     * @param $result
     *   A database result object.
     * @param $extended
     *   Return extended message details.
     *
     * @return array
     *   The record with some attributes themed.
     */
    protected function formatResult(\stdClass $result, bool $extended = false): array
    {
        // Database drivers differ on whether integer columns come back as ints.
        $result->wid = (int) $result->wid;
        $result->uid = (int) $result->uid;

        // Severity.
        $result->severity = $this->severityLabels()[$result->severity];

        // Date.
        $result->date = date('d/M H:i', (int)$result->timestamp);
        unset($result->timestamp);

        // Username.
        $result->username = (new AnonymousUserSession())->getAccountName() ?: 'Anonymous';
        $account = $this->entityTypeManager->getStorage('user')->load($result->uid);
        if ($account instanceof UserInterface && !$account->isAnonymous()) {
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

        return (array) $result;
    }

    /**
     * Resolves a --fields value against LABELS; '*' selects every field.
     *
     * @return string[]
     */
    protected function selectFields(?string $fields, array $default): array
    {
        if ($fields === null || $fields === '') {
            return $default;
        }
        if ($fields === '*') {
            return array_keys(self::LABELS);
        }
        $selected = StringUtils::csvToArray($fields);
        if ($unknown = array_diff($selected, array_keys(self::LABELS))) {
            throw new \InvalidArgumentException((string) $this->t('Unknown field(s): @fields', ['@fields' => implode(', ', $unknown)]));
        }
        return $selected;
    }

    /**
     * Renders records keyed by wid as a table, or as json/yaml.
     *
     * The table shows DEFAULT_FIELDS and json/yaml the whole record, unless
     * $fields narrows both.
     */
    protected function renderRecords(array $records, ?string $fields, ?OutputFormat $format): string
    {
        $selected = $this->selectFields($fields, $format === null ? self::DEFAULT_FIELDS : array_keys(self::LABELS));
        if ($fields !== null) {
            foreach ($records as &$record) {
                $record = array_intersect_key($record, array_flip($selected));
            }
            unset($record);
        }
        if ($format !== null) {
            return $this->encode($records, $format);
        }
        $rows = [];
        foreach ($records as $record) {
            $rows[] = array_map(fn(string $field) => (string) ($record[$field] ?? ''), $selected);
        }
        return $this->renderTable(array_map(fn(string $field) => self::LABELS[$field], $selected), $rows);
    }

    /**
     * Renders one record as a two-column label/value table, or as json/yaml.
     */
    protected function renderRecord(array $record, ?OutputFormat $format): string
    {
        if ($format !== null) {
            return $this->encode($record, $format);
        }
        $rows = [];
        foreach ($record as $field => $value) {
            $rows[] = [self::LABELS[$field] ?? $field, (string) $value];
        }
        return $this->renderTable([(string) $this->t('Field'), (string) $this->t('Value')], $rows);
    }

    protected function encode(array $data, OutputFormat $format): string
    {
        return match ($format) {
            OutputFormat::Json => json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($data),
        };
    }

    protected function renderTable(array $headers, array $rows): string
    {
        $buffer = new BufferedOutput();
        (new Table($buffer))->setHeaders($headers)->setRows($rows)->render();
        return $buffer->fetch();
    }

    /**
     * The distinct message types currently in the log.
     *
     * @return string[]
     */
    protected function messageTypes(): array
    {
        return $this->connection->query('SELECT DISTINCT([type]) FROM {watchdog} ORDER BY [type]')->fetchCol();
    }

    /**
     * Shell completion for --type.
     */
    public function suggestTypes(): array
    {
        return $this->messageTypes();
    }

    /**
     * Shell completion for --severity and --severity-min.
     */
    public function suggestSeverities(): array
    {
        return array_values($this->severityLabels());
    }
}
