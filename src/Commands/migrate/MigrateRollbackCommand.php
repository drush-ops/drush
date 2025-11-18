<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\Migrate\MigrateExecutable;
use Drush\Style\DrushStyle;
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
    description: 'Rollback one or more migrations.',
    aliases: ['mr', 'migrate-rollback'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[CLI\Version(version: '10.4')]
final class MigrateRollbackCommand extends Command
{
    use AutowireTrait;
    use MigrateRunnerTrait;

    public const string NAME = 'migrate:rollback';

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
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration tags to rollback')
            ->addOption('feedback', null, InputOption::VALUE_REQUIRED, 'Frequency of progress messages, in items processed')
            ->addOption('idlist', null, InputOption::VALUE_REQUIRED, "Comma-separated list of IDs to rollback. As an ID may have more than one column, concatenate the columns with the colon ':' separator")
            ->addOption('progress', null, InputOption::VALUE_NEGATABLE, 'Show progress bar', true)
            ->addUsage('migrate:rollback --all')
            ->addUsage('migrate:rollback --all --no-progress')
            ->addUsage('migrate:rollback --tag=user,main_content')
            ->addUsage('migrate:rollback classification,article')
            ->addUsage('migrate:rollback user --idlist=5')
            ->setHelp('Rollback all migrations or specific migrations, optionally filtered by tags.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $migrationIds = $input->getArgument('migrationIds');
        $tags = $input->getOption('tag');
        $all = $input->getOption('all');

        if (!$all && !$migrationIds && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag, or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migrationIds, $tags)) {
            $io->error(dt('No migrations found.'));
            return self::FAILURE;
        }

        $executableOptions = array_intersect_key(
            $input->getOptions(),
            array_flip(['feedback', 'idlist', 'progress'])
        );
        foreach ($list as $migrations) {
            // Rollback in reverse order.
            $migrations = array_reverse($migrations);
            foreach ($migrations as $migration) {
                $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $output, $executableOptions);
                // drush_op() provides --simulate support.
                drush_op([$executable, 'rollback']);
            }
        }

        return self::SUCCESS;
    }
}
