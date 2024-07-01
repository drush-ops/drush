<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class DrupalCommands extends DrushCommands
{
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

    public function __construct(protected CronInterface $cron, protected ModuleHandlerInterface $moduleHandler, protected RouteProviderInterface $routeProvider)
    {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('cron'),
            $container->get('module_handler'),
            $container->get('router.route_provider')
        );

        return $commandHandler;
    }

    /**
     * Run all cron hooks in all active modules for specified site.
     *
     * Consider using `drush maint:status && drush core:cron` to avoid cache poisoning during maintenance mode.
     */
    #[CLI\Command(name: self::CRON, aliases: ['cron', 'core-cron'])]
    #[CLI\Topics(topics: [DocsCommands::CRON])]
    public function cron(): void
    {
        $this->getCron()->run();
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

        $requirements = $this->getModuleHandler()->invokeAll('requirements', ['runtime']);
        $this->getModuleHandler()->alter('requirements', $requirements);
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
            $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
            $rows[$key] = [
                'title' => self::styleRow((string) $info['title'], $options['format'], $severity),
                'value' => self::styleRow(DrupalUtil::drushRender($info['value'] ?? ''), $options['format'], $severity),
                'description' => self::styleRow(DrupalUtil::drushRender($info['description'] ?? ''), $options['format'], $severity),
                'sid' => $severity,
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
