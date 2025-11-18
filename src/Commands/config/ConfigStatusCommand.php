<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'Display status of configuration (differences between the filesystem and database).',
    aliases: ['cst', 'config-status'],
)]
#[CLI\FieldLabels(labels: ['name' => 'Name', 'state' => 'State'])]
#[CLI\DefaultTableFields(fields: ['name', 'state'])]
#[CLI\FilterDefaultField(field: 'name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class ConfigStatusCommand extends Command
{
    use AutowireTrait;
    use ConfigTrait;
    use FormatterTrait;

    public const string NAME = 'config:status';

    public function __construct(
        protected readonly ConfigFactoryInterface $configFactory,
        #[Autowire(service: 'config.storage')]
        protected readonly StorageInterface $configStorage,
        protected ImportStorageTransformer $importStorageTransformer,
        protected readonly FormatterManager $formatterManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'A comma-separated list of states to filter results.', 'Only in DB,Only in sync dir,Different')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'The config prefix. For example, system. No prefix will return all names in the system.')
            ->addUsage('config:status --state=Identical')
            ->addUsage("config:status --state='Only in sync dir' --prefix=node.type.")
            ->addUsage('config:status --state=Any --format=list')
            ->addUsage('config:status 2>&1 | grep "No differences"');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): ?RowsOfFields
    {
        $state = $input->getOption('state');
        $prefix = $input->getOption('prefix');

        $config_list = array_fill_keys(
            $this->configFactory->listAll($prefix),
            'Identical'
        );

        $directory = $this->getDirectory();
        $storage = $this->getStorage($directory);
        $state_map = [
            'create' => 'Only in DB',
            'update' => 'Different',
            'delete' => 'Only in sync dir',
        ];

        foreach ($this->getChanges($storage) as $collection) {
            foreach ($collection as $operation => $configs) {
                foreach ($configs as $config) {
                    if (!$prefix || str_starts_with($config, $prefix)) {
                        $config_list[$config] = $state_map[$operation];
                    }
                }
            }
        }

        if ($state) {
            $allowed_states = explode(',', $state);
            if (!in_array('Any', $allowed_states)) {
                $config_list = array_filter($config_list, fn(string $state): bool => in_array($state, $allowed_states));
            }
        }

        ksort($config_list);

        $rows = [];
        $color_map = [
            'Only in DB' => 'green',
            'Only in sync dir' => 'red',
            'Different' => 'yellow',
            'Identical' => 'white',
        ];

        foreach ($config_list as $config => $state) {
            if ($input->getOption('format') == 'table' && $state != 'Identical') {
                $state = "<fg={$color_map[$state]};options=bold>$state</>";
            }
            $rows[$config] = [
                'name' => $config,
                'state' => $state,
            ];
        }

        if (!$rows) {
            $this->logger->notice('No differences between DB and sync directory.');

            // Suppress output if there are no differences and we are using the
            // human readable "table" formatter so that we not uselessly output
            // empty table headers.
            if ($input->getOption('format') === 'table') {
                return null;
            }
        }

        return new RowsOfFields($rows);
    }

    /**
     * Returns the difference in configuration between active storage and target storage.
     */
    private function getChanges($target_storage): array
    {
        $target_storage = $this->importStorageTransformer->transform($target_storage);

        $config_comparer = new StorageComparer($this->configStorage, $target_storage);

        $change_list = [];
        if ($config_comparer->createChangelist()->hasChanges()) {
            foreach ($config_comparer->getAllCollectionNames() as $collection) {
                $change_list[$collection] = $config_comparer->getChangelist(null, $collection);
            }
        }
        return $change_list;
    }
}
