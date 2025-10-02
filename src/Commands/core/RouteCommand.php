<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'View information about all routes or one route.',
    aliases: ['route'],
)]
#[CLI\Version(version: '10.5')]
#[CLI\Formatter(returnType: UnstructuredListData::class, defaultFormatter: 'yaml')]
final class RouteCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'core:route';

    public function __construct(
        protected readonly RouteProviderInterface $routeProvider,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'A route name.')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'An internal path or URL.')
            ->addUsage('route --name=update.status')
            ->addUsage('route --path=/user/1')
            ->addUsage('route --url=https://example.com/node/1');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): array
    {
        $route = $items = null;
        $provider = $this->routeProvider;

        if ($path = $input->getOption('path')) {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $path = parse_url($path, PHP_URL_PATH);
                // Strip base path.
                $path = '/' . substr_replace($path, '', 0, strlen(base_path()));
            }
            $name = Url::fromUserInput($path)->getRouteName();
            $route = $provider->getRouteByName($name);
        } elseif ($name = $input->getOption('name')) {
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
}
