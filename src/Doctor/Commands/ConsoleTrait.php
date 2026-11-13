<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands;

use Drupal\Core\Command\DrupalConsoleLogger;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Console helpers shared by the commands that run under dr only.
 */
trait ConsoleTrait
{
    use StringTranslationTrait;

    /**
     * Sends Drupal log messages to the console.
     *
     * dr does this once per command through ConsoleSubscriber, but a container
     * rebuild replaces logger.console with an instance that has no console
     * logger attached, so it is done again after every rebuild. It also lets
     * kernel tests see the log output.
     */
    protected function attachConsoleLogger(ContainerInterface $container, OutputInterface $output): void
    {
        if (!$container->has('logger.console')) {
            return;
        }
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $container->get('logger.console')->setLogger(new ConsoleLogger($errorOutput, DrupalConsoleLogger::verbosityLevelMap()));
    }

    /**
     * batch_process() builds a URL from the current route; dr's request has
     * none, so give it the placeholder route kernel tests use.
     */
    protected function ensureRoute(ContainerInterface $container): void
    {
        $request = $container->get('request_stack')->getCurrentRequest();
        if ($request && !$request->attributes->has(RouteObjectInterface::ROUTE_OBJECT)) {
            $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('<none>'));
            $request->attributes->set(RouteObjectInterface::ROUTE_NAME, '<none>');
        }
    }

    /**
     * @param string[] $headers
     * @param array[] $rows
     */
    protected function table(array $headers, array $rows): string
    {
        $buffer = new BufferedOutput();
        (new Table($buffer))->setHeaders($headers)->setRows($rows)->render();
        return $buffer->fetch();
    }

    /**
     * Renders markup, a render array or a string as plain console text.
     */
    protected function plainText(mixed $value, ?ContainerInterface $container = null): string
    {
        if (is_array($value)) {
            $value = $container?->get('renderer')->renderInIsolation($value) ?? '';
        }
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }
}
