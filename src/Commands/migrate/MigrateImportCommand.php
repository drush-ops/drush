<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\Migrate\MigrateExecutable;
use Drush\Drupal\Migrate\MigrateUtils;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'Perform one or more migration processes.',
    aliases: ['mim', 'migrate-import'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[CLI\Version(version: '10.4')]
final class MigrateImportCommand extends Command
{
    use AutowireTrait;
    use MigrateRunnerTrait;

    public const string NAME = 'migrate:import';

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
            ->addArgument('migrationIds', InputArgument::OPTIONAL, 'Comma-separated list of migration IDs.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process all migrations')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration tags to import')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit on the number of items to process in each migration')
            ->addOption('feedback', null, InputOption::VALUE_REQUIRED, 'Frequency of progress messages, in items processed')
            ->addOption('idlist', null, InputOption::VALUE_REQUIRED, "Comma-separated list of IDs to import. As an ID may have more than one column, concatenate the columns with the colon ':' separator")
            ->addOption('update', null, InputOption::VALUE_NONE, 'In addition to processing unprocessed items from the source, update previously-imported items with the current data')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force an operation to run, even if all dependencies are not satisfied')
            ->addOption('execute-dependencies', null, InputOption::VALUE_NONE, 'Execute all dependent migrations first')
            ->addOption('timestamp', null, InputOption::VALUE_NONE, 'Show progress ending timestamp in progress messages')
            ->addOption('total', null, InputOption::VALUE_NONE, 'Show total processed item number in progress messages')
            ->addOption('progress', null, InputOption::VALUE_NEGATABLE, 'Show progress bar', true)
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete destination records missed from the source. Not compatible with <info>--limit</info> and <info>--idlist</info> options, and high_water_property source configuration key.')
            ->addUsage('migrate:import --all')
            ->addUsage('migrate:import --all --no-progress')
            ->addUsage('migrate:import --tag=user,main_content')
            ->addUsage('migrate:import classification,article')
            ->addUsage('migrate:import user --limit=2')
            ->addUsage('migrate:import user --idlist=5')
            ->addUsage('migrate:import node_revision --idlist=1:2,2:3,3:5')
            ->addUsage('migrate:import user --limit=50 --feedback=20')
            ->addUsage('migrate:import --all --delete')
            ->setHelp('Perform all migrations or import specific migrations with various options for limiting, updating, and progress tracking.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrationIds = $input->getArgument('migrationIds');
        $tags = $input->getOption('tag');
        $all = $input->getOption('all');

        if (!$all && !$migrationIds && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migrationIds, $tags)) {
            throw new \Exception(dt('No migrations found.'));
        }

        $userData = [
            'options' => array_intersect_key($input->getOptions(), array_flip([
                'limit',
                'feedback',
                'idlist',
                'update',
                'force',
                'timestamp',
                'total',
                'progress',
                'delete',
            ])),
            'execute_dependencies' => $input->getOption('execute-dependencies'),
            'output' => $output,
        ];

        foreach ($list as $migrations) {
            array_walk($migrations, [$this, 'executeMigration'], $userData);
        }

        return self::SUCCESS;
    }

    /**
     * Executes a single migration.
     *
     * If the --execute-dependencies option was given, the migration's
     * dependencies will also be executed first.
     *
     * @param MigrationInterface $migration
     *   The migration to execute.
     * @param string $migrationId
     *   The migration ID (not used, just an artifact of array_walk()).
     * @param array $userData
     *   Additional data passed to the callback.
     * @throws PluginException
     */
    protected function executeMigration(MigrationInterface $migration, string $migrationId, array $userData): void
    {
        static $executedMigrations = [];

        if ($userData['execute_dependencies']) {
            $dependencies = $migration->getMigrationDependencies()['required'];
            // Remove already executed migrations.
            $dependencies = array_diff($dependencies, $executedMigrations);
            if ($dependencies) {
                $requiredMigrations = $this->migrationPluginManager->createInstances($dependencies);
                array_walk($requiredMigrations, [$this, 'executeMigration'], $userData);
            }
        }
        if (!empty($userData['options']['force'])) {
            // @todo Use the new MigrationInterface::setRequirements() method,
            //   instead of Migration::set() and remove the PHPStan exception
            //   from phpstan-baseline.neon when #2796755 lands in Drupal core.
            // @see https://www.drupal.org/i/2796755
            $migration->set('requirements', []);
        }
        if (!empty($userData['options']['update'])) {
            if (empty($userData['options']['idlist'])) {
                $migration->getIdMap()->prepareUpdate();
            } else {
                $sourceIdValuesList = MigrateUtils::parseIdList($userData['options']['idlist']);
                $keys = array_keys($migration->getSourcePlugin()->getIds());
                foreach ($sourceIdValuesList as $sourceIdValues) {
                    $migration->getIdMap()->setUpdate(array_combine($keys, $sourceIdValues));
                }
            }
        }

        $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $userData['output'], $userData['options']);
        // drush_op() provides --simulate support.
        drush_op($executable->import(...));
        if ($count = $executable->getFailedCount()) {
            // Nudge Drush to use a non-zero exit code.
            throw new \Exception(dt('!name migration: !count failed.', ['!name' => $migrationId, '!count' => $count]));
        }

        // Keep track of executed migrations.
        $executedMigrations[] = $migrationId;
    }
}
