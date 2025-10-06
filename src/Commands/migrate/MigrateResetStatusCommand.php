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
    description: "Reset an active migration's status to idle.",
    aliases: ['mrs', 'migrate-reset-status'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[ValidateMigrationId()]
#[CLI\Version(version: '10.4')]
final class MigrateResetStatusCommand extends Command
{
    use AutowireTrait;
    use MigrateRunnerTrait;

    public const NAME = 'migrate:reset-status';

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
            ->addArgument('migrationId', InputArgument::REQUIRED, 'The ID of migration to reset.')
            ->setHelp("Reset an active migration's status to idle.");
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
        $status = $migration->getStatus();
        if ($status == MigrationInterface::STATUS_IDLE) {
            $io->warning(dt('Migration @id is already Idle', ['@id' => $migrationId]));
        } else {
            $migration->setStatus(MigrationInterface::STATUS_IDLE);
            $io->success(dt('Migration @id reset to Idle', ['@id' => $migrationId]));
        }

        return self::SUCCESS;
    }
}
