<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use JetBrains\PhpStorm\Deprecated;

final class MigrateRunnerCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use MigrateStatusCommand::NAME')]
    const string STATUS = 'migrate:status';
    #[Deprecated(reason: 'Use MigrateImportCommand::NAME')]
    const string IMPORT = 'migrate:import';
    #[Deprecated(reason: 'Use MigrateRollbackCommand::NAME')]
    const string ROLLBACK = 'migrate:rollback';
    #[Deprecated(reason: 'Use MigrateStopCommand::NAME')]
    const string STOP = 'migrate:stop';
    #[Deprecated(reason: 'Use MigrateResetStatusCommand::NAME')]
    const string RESET_STATUS = 'migrate:reset-status';
    #[Deprecated(reason: 'Use MigrateMessagesCommand::NAME')]
    const string MESSAGES = 'migrate:messages';
    #[Deprecated(reason: 'Use MigrateFieldsSourceCommand::NAME')]
    const string FIELDS_SOURCE = 'migrate:fields-source';

    protected ?MigrationPluginManagerInterface $migrationPluginManager = null;

    public function __construct()
    {
        parent::__construct();

        $container = Drush::getContainer();
        if ($container->has('plugin.manager.migration')) {
            $this->setMigrationPluginManager($container->get('plugin.manager.migration'));
        }
    }

    /**
     * Provide a migration plugin manager.
     */
    public function setMigrationPluginManager(MigrationPluginManagerInterface $migrationPluginManager): void
    {
        $this->migrationPluginManager = $migrationPluginManager;
    }

    /**
     * Validates a migration ID is valid.
     *
     * If the argument to be validated is not named migrationId, pass the
     * argument name as the value of the annotation.
     */
    #[Deprecated(reason: 'Console commands are validated by MigrationIdListener.')]
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, selector: 'validate-migration-id')]
    public function validateMigrationId(CommandData $commandData): ?CommandError
    {
        $argName = $commandData->annotationData()->get('validate-migration-id') ?: 'migrationId';
        $migrationId = $commandData->input()->getArgument($argName);
        if (!$this->migrationPluginManager->hasDefinition($migrationId)) {
            return new CommandError(dt('Migration "@id" does not exist', ['@id' => $migrationId]));
        }
        return null;
    }
}
