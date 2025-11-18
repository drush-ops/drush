<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\Migrate\ValidateMigrationId;
use Drush\Style\DrushStyle;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'Stop a migration that is currently executing.',
    aliases: ['mst', 'migrate-stop'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[ValidateMigrationId()]
#[CLI\Version(version: '10.4')]
final class MigrateStopCommand extends Command
{
    use AutowireTrait;
    use MigrateRunnerTrait;

    public const string NAME = 'migrate:stop';

    public function __construct(
        #[Autowire(service: 'keyvalue')]
        protected readonly KeyValueFactoryInterface $keyValueFactory,
        protected readonly ContainerInterface $container,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
        $this->keyValue = $keyValueFactory->get('migrate_last_imported');

        if ($container->has('plugin.manager.migration')) {
            $this->setMigrationPluginManager($container->get('plugin.manager.migration'));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('migrationId', InputArgument::REQUIRED, 'The ID of migration to stop.');
    }

    /**
     * @throws PluginException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $migrationId = $input->getArgument('migrationId');

        /** @var MigrationInterface $migration */
        $migration = $this->migrationPluginManager->createInstance($migrationId);
        switch ($migration->getStatus()) {
            case MigrationInterface::STATUS_IDLE:
                $this->logger->warning(dt('Migration @id is idle', ['@id' => $migrationId]));
                break;
            case MigrationInterface::STATUS_DISABLED:
                $this->logger->warning(dt('Migration @id is disabled', ['@id' => $migrationId]));
                break;
            case MigrationInterface::STATUS_STOPPING:
                $this->logger->warning(dt('Migration @id is already stopping', ['@id' => $migrationId]));
                break;
            default:
                $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
                $io->success(dt('Migration @id requested to stop', ['@id' => $migrationId]));
                break;
        }

        return self::SUCCESS;
    }
}
