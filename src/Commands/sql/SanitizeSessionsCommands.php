<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class is a good example of how to build a sql-sanitize plugin.
 */
final class SanitizeSessionsCommands extends DrushCommands implements SanitizePluginInterface
{
    public function __construct(protected Connection $database)
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
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }


    /**
     * Sanitize sessions from the DB.
     */
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'sql-sanitize')]
    public function sanitize($result, CommandData $commandData): void
    {
        $this->getDatabase()->truncate('sessions')->execute();
        $this->logger()->success(dt('Sessions table truncated.'));
    }

    #[CLI\Hook(type: HookManager::ON_EVENT, target: 'sql-sanitize-confirms')]
    public function messages(&$messages, InputInterface $input): void
    {
        $messages[] = dt('Truncate sessions table.');
    }
}
