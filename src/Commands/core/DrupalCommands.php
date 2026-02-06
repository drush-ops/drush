<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\CronInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;
use Drush\Utils\StringUtils;

final class DrupalCommands extends DrushCommands
{
    use AutowireTrait;

    const CRON = 'core:cron';
    const REQUIREMENTS = 'core:requirements';
    const ROUTE = 'core:route';

    public function getCron(): CronInterface
    {
        return $this->cron;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
    }

    public function getRouteProvider(): RouteProviderInterface
    {
        return $this->routeProvider;
    }

    public function __construct(
        protected Connection $connection,
        protected CronInterface $cron,
        protected ModuleHandlerInterface $moduleHandler,
        protected RouteProviderInterface $routeProvider
    ) {
        parent::__construct();
    }

    /**
     * Run all cron hooks in all active modules for specified site.
     *
     * Consider using `drush maint:status && drush core:cron` to avoid cache poisoning during maintenance mode.
     *
     * @command core:cron
     * @aliases cron,core-cron
     * @topics docs:cron
     * @option show-drupal-logs Display Drupal watchdog logs generated during cron execution.
     * @option log-severity Minimum severity level for displayed logs (emergency=0, alert=1, critical=2, error=3, warning=4, notice=5, info=6, debug=7). Defaults to 6 (info).
     * @option log-type Filter logs by type (e.g., 'cron', 'php', 'system').
     * @usage drush core:cron
     *   Run cron normally.
     * @usage drush core:cron --show-drupal-logs
     *   Run cron and display Drupal logs generated during execution.
     * @usage drush core:cron --show-drupal-logs --log-severity=4
     *   Run cron and show only warnings and higher severity logs.
     * @usage drush core:cron --show-drupal-logs --log-type=cron
     *   Run cron and show only cron-related logs.
     */
    #[CLI\Command(name: self::CRON, aliases: ['cron', 'core-cron'])]
    #[CLI\Option(name: 'show-drupal-logs', description: 'Display Drupal watchdog logs generated during cron execution.')]
    #[CLI\Option(name: 'log-severity', description: 'Minimum severity level for displayed logs (0-7). Defaults to 6 (info).')]
    #[CLI\Option(name: 'log-type', description: 'Filter logs by type.')]
    #[CLI\Topics(topics: ['docs:cron'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function cron($options = [
        'show-drupal-logs' => false,
        'log-severity' => 6,
        'log-type' => self::REQ,
    ]): void
    {
        // Get the last watchdog ID before running cron
        $lastWid = null;
        if ($options['show-drupal-logs']) {
            // Only check if dblog module is enabled
            if (!$this->moduleHandler->moduleExists('dblog')) {
                throw new \Exception(dt('The dblog module must be enabled to use --show-drupal-logs option.'));
            }
            $lastWid = $this->getLastWatchdogId();
        }

        // Run cron
        $this->getCron()->run();

        // Display Drupal logs if requested
        if ($options['show-drupal-logs'] && $lastWid !== null) {
            $this->displayCronLogs($lastWid, (int) $options['log-severity'], $options['log-type']);
        }
    }

    /**
     * Get the last watchdog entry ID.
     *
     * @return int|null
     */
    protected function getLastWatchdogId(): ?int
    {
        try {
            $result = $this->connection->select('watchdog', 'w')
                ->fields('w', ['wid'])
                ->orderBy('wid', 'DESC')
                ->range(0, 1)
                ->execute()
                ->fetchField();

            return $result ? (int) $result : 0;
        } catch (\Exception $e) {
            $this->logger()->warning('Could not retrieve last watchdog ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Display watchdog logs generated since the given ID.
     *
     * @param int $last_wid The last watchdog ID before cron ran
     * @param int $min_severity Minimum severity level to display
     * @param string|null $log_type Optional type filter
     */
    protected function displayCronLogs(int $last_wid, int $min_severity, ?string $log_type): void
    {
        $query = $this->connection->select('watchdog', 'w')
            ->fields('w', ['wid', 'timestamp', 'type', 'severity', 'message', 'variables'])
            ->condition('wid', $last_wid, '>')
            ->orderBy('wid', 'ASC');

        if ($min_severity < 7) {
            $query->condition('severity', $min_severity, '<=');
        }

        if ($log_type) {
            $query->condition('type', $log_type);
        }

        $logs = $query->execute()->fetchAll();

        if (!empty($logs)) {
            $this->logger()->notice(dt('Drupal logs generated during cron execution:'));
            $this->logger()->notice(str_repeat('-', 80));

            foreach ($logs as $log) {
                $timestamp = date('Y-m-d H:i:s', (int) $log->timestamp);

                $severity_labels = [
                    0 => 'EMERGENCY',
                    1 => 'ALERT',
                    2 => 'CRITICAL',
                    3 => 'ERROR',
                    4 => 'WARNING',
                    5 => 'NOTICE',
                    6 => 'INFO',
                    7 => 'DEBUG',
                ];

                $severity_label = $severity_labels[$log->severity] ?? 'UNKNOWN';

                // Format message
                $message = $this->formatLogMessage($log->message, $log->variables);

                // Output using logger based on severity
                if ($log->severity <= 2) {
                    $this->logger()->error(dt("[$timestamp] [$severity_label] [{$log->type}] $message"));
                } elseif ($log->severity <= 4) {
                    $this->logger()->warning(dt("[$timestamp] [$severity_label] [{$log->type}] $message"));
                } else {
                    $this->logger()->notice(dt("[$timestamp] [$severity_label] [{$log->type}] $message"));
                }
            }

            $this->logger()->notice(str_repeat('-', 80));
            $this->logger()->success(dt('Total logs displayed: @count', ['@count' => count($logs)]));
        } else {
            $this->logger()->notice(dt('No Drupal logs generated during cron execution.'));
        }
    }

    /**
     * Format a log message by replacing placeholders and sanitizing content.
     *
     * @param string $message
     *   The log message with placeholders (e.g., 'User @name logged in').
     * @param string|null $variables
     *   Serialized array of placeholder replacements.
     *
     * @return string
     *   The formatted message with HTML stripped and truncated if needed.
     */
    protected function formatLogMessage(string $message, ?string $variables): string
    {
        if (!empty($variables)) {
            $variables_array = @unserialize($variables);
            if (is_array($variables_array)) {
                $message = strtr($message, $variables_array);
            }
        }

        // Strip HTML tags
        $message = strip_tags($message);

        // Truncate long messages
        if (strlen($message) > 200) {
            $message = substr($message, 0, 197) . '...';
        }

        return $message;
    }

    /**
     * Information about things that may be wrong in your Drupal installation.
     */
    #[CLI\Command(name: self::REQUIREMENTS, aliases: ['status-report', 'rq', 'core-requirements'])]
    #[CLI\Option(name: 'severity', description: 'Only show status report messages with a severity greater than or equal to the specified value.')]
    #[CLI\Option(name: 'ignore', description: 'Comma-separated list of requirements to remove from output. Run with --format=yaml to see key values to use.')]
    #[CLI\Usage(name: 'drush core:requirements', description: 'Show all status lines from the Status Report admin page.')]
    #[CLI\Usage(name: 'drush core:requirements --severity=2', description: 'Show only the red lines from the Status Report admin page.')]
    #[CLI\FieldLabels(labels: [
        'title' => 'Title',
        'severity' => 'Severity',
        'sid' => 'SID',
        'description' => 'Description',
        'value' => 'Summary',
    ])]
    #[CLI\DefaultTableFields(fields: ['title', 'severity', 'value'])]
    #[CLI\FilterDefaultField(field: 'severity')]
    public function requirements($options = ['format' => 'table', 'severity' => -1, 'ignore' => '']): RowsOfFields
    {
        include_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        $severities = [
            REQUIREMENT_INFO => dt('Info'),
            REQUIREMENT_OK => dt('OK'),
            REQUIREMENT_WARNING => dt('Warning'),
            REQUIREMENT_ERROR => dt('Error'),
        ];

        drupal_load_updates();

        $requirements = $this->moduleHandler->invokeAll('requirements', ['runtime']);
        $runtime_requirements = $this->moduleHandler->invokeAll('runtime_requirements');
        $requirements = array_merge($requirements, $runtime_requirements);
        $this->moduleHandler->alter('requirements', $requirements);
        $this->moduleHandler->alter('runtime_requirements', $requirements);
        // If a module uses "$requirements[] = " instead of
        // "$requirements['label'] = ", then build a label from
        // the title.
        foreach ($requirements as $key => $info) {
            if (is_numeric($key)) {
                unset($requirements[$key]);
                $new_key = strtolower(str_replace(' ', '_', (string) $info['title']));
                $requirements[$new_key] = $info;
            }
        }
        $ignore_requirements = StringUtils::csvToArray($options['ignore']);
        foreach ($ignore_requirements as $ignore) {
            unset($requirements[$ignore]);
        }
        ksort($requirements);

        $min_severity = $options['severity'];
        foreach ($requirements as $key => $info) {
            // Adjust once Drupal 11.1- is unsupported.
            $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
            if (is_object($severity)) {
                $severity = $severity->value;
            }

            $rows[$key] = [
                'title' => $this->styleRow((string) $info['title'], $options['format'], $severity),
                'value' => $this->styleRow(DrupalUtil::drushRender($info['value'] ?? ''), $options['format'], $severity),
                'description' => $this->styleRow(DrupalUtil::drushRender($info['description'] ?? ''), $options['format'], $severity),
                'sid' => $severity,
                'severity' => $this->styleRow(@$severities[$severity], $options['format'], $severity)
            ];
            if ($severity < $min_severity) {
                unset($rows[$key]);
            }
        }
        return new RowsOfFields($rows ?? []);
    }

    /**
     * View information about all routes or one route.
     */
    #[CLI\Command(name: self::ROUTE, aliases: ['route'])]
    #[CLI\Usage(name: 'drush route', description: 'View all routes.')]
    #[CLI\Usage(name: 'drush route --name=update.status', description: 'View details about the <info>update.status</info> route.')]
    #[CLI\Usage(name: 'drush route --path=/user/1', description: 'View details about the <info>entity.user.canonical</info> route.')]
    #[CLI\Usage(name: 'drush route --url=https://example.com/node/1', description: 'View details about the <info>entity.node.canonical</info> route.')]
    #[CLI\Option(name: 'name', description: 'A route name.')]
    #[CLI\Option(name: 'path', description: 'An internal path or URL.')]
    #[CLI\Version(version: '10.5')]
    public function route($options = ['name' => self::REQ, 'path' => self::REQ, 'format' => 'yaml'])
    {
        $route = $items = null;
        $provider = $this->getRouteProvider();
        if ($path = $options['path']) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $path = parse_url($path, PHP_URL_PATH);
                // Strip base path.
                $path = '/' . substr_replace($path, '', 0, strlen(base_path()));
            }
            $name = Url::fromUserInput($path)->getRouteName();
            $route = $provider->getRouteByName($name);
        } elseif ($name = $options['name']) {
            $route = $provider->getRouteByName($name);
        }
        if ($route) {
            $route = $provider->getRouteByName($name);
            $return = [
              'name' => $name,
              'path' => $route->getPath(),
              'defaults' => $route->getDefaults(),
              'requirements' => $route->getRequirements(),
              'options' => $route->getOptions(),
                // Rarely useful parts are commented out.
                //  'condition' => $route->getCondition(),
                //  'methods' => $route->getMethods(),
            ];
            unset($return['options']['compiler_class'], $return['options']['utf8']);
            return $return;
        }

        // Just show a list of all routes.
        $routes = $provider->getAllRoutes();
        foreach ($routes as $route_name => $route) {
            $items[$route_name] = $route->getPath();
        }
        return $items;
    }

    private function styleRow($content, $format, $severity): ?string
    {
        if (
            !in_array($format, [
            'sections',
            'table',
            ])
        ) {
            return $content;
        }

        switch ($severity) {
            case REQUIREMENT_OK:
                return '<info>' . $content . '</>';
            case REQUIREMENT_WARNING:
                return '<comment>' . $content . '</>';
            case REQUIREMENT_ERROR:
                return '<fg=red>' . $content . '</>';
            case REQUIREMENT_INFO:
            default:
                return $content;
        }
    }
}
