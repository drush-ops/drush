<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\Migrate\ValidateMigrationId;
use Drush\Formatters\FormatterTrait;
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
    description: 'List the fields available for mapping in a source.',
    aliases: ['mfs', 'migrate-fields-source'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[ValidateMigrationId()]
#[CLI\FieldLabels(labels: [
    'machine_name' => 'Field name',
    'description' => 'Description',
])]
#[CLI\DefaultFields(fields: ['machine_name', 'description'])]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\Version(version: '10.4')]
final class MigrateFieldsSourceCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use MigrateRunnerTrait;

    public const string NAME = 'migrate:fields-source';

    public function __construct(
        #[Autowire(service: 'keyvalue')]
        protected readonly KeyValueFactoryInterface $keyValueFactory,
        protected readonly ContainerInterface $container,
        protected readonly FormatterManager $formatterManager,
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
            ->addArgument('migrationId', InputArgument::REQUIRED, 'The ID of the migration.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $migrationId = $input->getArgument('migrationId');

        /** @var MigrationInterface $migration */
        $migration = $this->migrationPluginManager->createInstance($migrationId);
        $source = $migration->getSourcePlugin();
        $table = [];
        foreach ($source->fields() as $machineName => $description) {
            $table[] = [
                'machine_name' => $machineName,
                'description' => strip_tags((string) $description),
            ];
        }
        return new RowsOfFields($table);
    }
}
