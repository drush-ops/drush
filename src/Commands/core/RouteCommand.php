<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'View information about all routes or one route.',
    aliases: ['route'],
    usages: [
        'route --name=update.status',
        'route --path=/user/1',
        'route --path=https://example.com/node/1',
    ],
)]
final class RouteCommand
{
    use AutowireTrait;

    public const string NAME = 'core:route';

    public function __construct(
        protected RouteProviderInterface $routeProvider,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('A route name.')]
        ?string $name = null,
        #[Option('An internal path or URL.')]
        ?string $path = null,
        #[Option('An alternative output format such as json. Defaults to yaml.')]
        ?OutputFormat $format = null,
    ): int {
        if ($path !== null && $path !== '') {
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $path = (string) parse_url($path, PHP_URL_PATH);
                // Strip base path.
                $path = '/' . substr_replace($path, '', 0, strlen(base_path()));
            }
            $name = Url::fromUserInput($path)->getRouteName();
        }

        if ($name !== null && $name !== '') {
            try {
                $route = $this->routeProvider->getRouteByName($name);
            } catch (RouteNotFoundException) {
                throw new \InvalidArgumentException(sprintf('Route %s does not exist.', $name));
            }
            $data = [
                'name' => $name,
                'path' => $route->getPath(),
                'defaults' => $route->getDefaults(),
                'requirements' => $route->getRequirements(),
                'options' => $route->getOptions(),
                // Rarely useful parts are left out: condition and methods.
            ];
            unset($data['options']['compiler_class'], $data['options']['utf8']);
        } else {
            // Just show a list of all routes.
            $data = [];
            foreach ($this->routeProvider->getAllRoutes() as $routeName => $route) {
                $data[$routeName] = $route->getPath();
            }
        }

        $data = $this->normalize($data);
        $result = match ($format) {
            OutputFormat::Json => json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            default => Yaml::encode($data),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $output->writeln(rtrim($result, "\n"), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /**
     * Replaces objects in route data with values yaml and json can encode.
     *
     * Route defaults often hold a TranslatableMarkup title; anything
     * Stringable becomes its string, other objects become their public
     * properties.
     */
    protected function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->normalize(...), $value);
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        if (is_object($value)) {
            return $this->normalize(get_object_vars($value));
        }
        return $value;
    }
}
