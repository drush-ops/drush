<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Drupal\Migrate\ValidateMigrationId;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidateMigrationIdListener
{
    public function __construct(
        private readonly MigrationPluginManagerInterface $migrationPluginManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function create(ContainerInterface $container)
    {
        if ($container->has('plugin.manager.migration')) {
            return new self($container->get('plugin.manager.migration'), $container->get('logger'));
        }
        // Do nothing. Command never gets added to the Application.
    }

    /**
     *  This subscriber affects commands which put #[ValidateMigrationId] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(ValidateMigrationId::class);
        if (empty($attributes)) {
            return;
        }
        /** @var ValidateMigrationId $instance */
        $instance = $attributes[0]->newInstance();
        // $argName = $commandData->annotationData()->get('validate-migration-id') ?: 'migrationId';
        $migrationId = $event->getInput()->getArgument('migrationId');
        if (!$this->migrationPluginManager->hasDefinition($migrationId)) {
            $this->logger->error('Migration "{id}" does not exist', ['id' => $migrationId]);
            $event->disableCommand();
        }
    }
}
