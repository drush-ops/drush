<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;
use Drush\Utils\StringUtils;

class DrupalCommands extends DrushCommands
{
    /**
     * @var CronInterface
     */
    protected $cron;

    /**
     * @var ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * @var RouteProviderInterface
     */
    protected $routeProvider;

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

    /**
     * @param CronInterface $cron
     * @param ModuleHandlerInterface $moduleHandler
     * @param RouteProviderInterface $routeProvider
     */
    public function __construct(CronInterface $cron, ModuleHandlerInterface $moduleHandler, RouteProviderInterface $routeProvider)
    {
        $this->cron = $cron;
        $this->moduleHandler = $moduleHandler;
        $this->routeProvider = $routeProvider;
    }

    /**
     * Run all cron hooks in all active modules for specified site.
     *
     * @command core:cron
     * @aliases cron,core-cron
     * @topics docs:cron
     */
    public function cron(): void
    {
        $this->getCron()->run();
    }

    /**
     * Information about things that may be wrong in your Drupal installation.
     *
     * @command core:requirements
     * @option severity Only show status report messages with a severity greater than or equal to the specified value.
     * @option ignore Comma-separated list of requirements to remove from output. Run with --format=yaml to see key values to use.
     * @aliases status-report,rq,core-requirements
     * @usage drush core:requirements
     *   Show all status lines from the Status Report admin page.
     * @usage drush core:requirements --severity=2
     *   Show only the red lines from the Status Report admin page.
     * @table-style default
     * @field-labels
     *   title: Title
     *   severity: Severity
     *   sid: SID
     *   description: Description
     *   value: Summary
     * @default-fields title,severity,value
     * @filter-default-field severity
     */
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

        $requirements = $this->getModuleHandler()->invokeAll('requirements', ['runtime']);
        // If a module uses "$requirements[] = " instead of
        // "$requirements['label'] = ", then build a label from
        // the title.
        foreach ($requirements as $key => $info) {
            if (is_numeric($key)) {
                unset($requirements[$key]);
                $new_key = strtolower(str_replace(' ', '_', $info['title']));
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
            $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
            $rows[$key] = [
                'title' => self::styleRow((string) $info['title'], $options['format'], $severity),
                'value' => self::styleRow(DrupalUtil::drushRender($info['value'] ?? ''), $options['format'], $severity),
                'description' => self::styleRow(DrupalUtil::drushRender($info['description'] ?? ''), $options['format'], $severity),
                'sid' => self::styleRow($severity, $options['format'], $severity),
                'severity' => self::styleRow(@$severities[$severity], $options['format'], $severity)
            ];
            if ($severity < $min_severity) {
                unset($rows[$key]);
            }
        }
        return new RowsOfFields($rows);
    }

    /**
     * View information about all routes or one route.
     *
     * @command core:route
     * @aliases route
     * @usage drush route
     *   View all routes.
     * @usage drush route --name=update.status
     *   View details about the <info>update.status</info> route.
     * @usage drush route --path=user/1
     *   View details about the <info>entity.user.canonical</info> route.
     * @option name A route name.
     * @option path An internal path.
     * @version 10.5
     */
    public function route($options = ['name' => self::REQ, 'path' => self::REQ, 'format' => 'yaml'])
    {
        $route = $items = null;
        $provider = $this->getRouteProvider();
        if ($path = $options['path']) {
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

    private static function styleRow($content, $format, $severity): ?string
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
