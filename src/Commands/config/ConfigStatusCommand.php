<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Display status of configuration (differences between the filesystem and database).',
    aliases: ['cst', 'config-status'],
    usages: [
        'config:status --state=Identical',
        "config:status --state='Only in sync dir' --prefix=node.type.",
        'config:status --state=Any --format=json',
        'config:status 2>&1 | grep "No differences"',
    ],
)]
final class ConfigStatusCommand
{
    use AutowireTrait;
    use StringTranslationTrait;

    public const string NAME = 'config:status';

    /**
     * Values --state accepts, besides Any.
     */
    public const array STATES = ['Identical', 'Only in DB', 'Only in sync dir', 'Different'];

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        #[Autowire(service: 'config.storage')]
        protected StorageInterface $configStorage,
        #[Autowire(service: 'config.storage.sync')]
        protected StorageInterface $configStorageSync,
        protected ImportStorageTransformer $importStorageTransformer,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('A comma-separated list of states to filter results: Identical, Only in DB, Only in sync dir, Different, or Any.', suggestedValues: ['Any', ...self::STATES])]
        string $state = 'Only in DB,Only in sync dir,Different',
        #[Option('The config prefix. For example, system. No prefix will return all names in the system.')]
        ?string $prefix = null,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $prefix ??= '';
        $config_list = array_fill_keys($this->configFactory->listAll($prefix), 'Identical');

        $state_map = [
            'create' => 'Only in DB',
            'update' => 'Different',
            'delete' => 'Only in sync dir',
        ];
        foreach ($this->getChanges() as $collection) {
            foreach ($collection as $operation => $configs) {
                foreach ($configs as $config) {
                    if ($prefix === '' || str_starts_with($config, $prefix)) {
                        $config_list[$config] = $state_map[$operation];
                    }
                }
            }
        }

        $allowed_states = explode(',', $state);
        if ($state !== '' && !in_array('Any', $allowed_states)) {
            $config_list = array_filter($config_list, fn(string $state): bool => in_array($state, $allowed_states));
        }
        ksort($config_list);

        if (!$config_list) {
            // On stderr, so that json and yaml output stays parseable.
            (new SymfonyStyle($input, $output))->getErrorStyle()->note((string) $this->t('No differences between DB and sync directory.'));
            // An empty table would be nothing but headers.
            if ($format === null) {
                return Command::SUCCESS;
            }
        }

        if ($format === null) {
            $color_map = [
                'Only in DB' => 'green',
                'Only in sync dir' => 'red',
                'Different' => 'yellow',
            ];
            $rows = [];
            foreach ($config_list as $name => $state) {
                $rows[] = [$name, isset($color_map[$state]) ? "<fg={$color_map[$state]};options=bold>$state</>" : $state];
            }
            (new Table($output))
                ->setHeaders([(string) $this->t('Name'), (string) $this->t('State')])
                ->setRows($rows)
                ->render();
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($config_list as $name => $state) {
            $rows[$name] = ['name' => $name, 'state' => $state];
        }
        $result = match ($format) {
            OutputFormat::Json => json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($rows),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $output->writeln(rtrim($result, "\n"), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /**
     * Returns what importing the sync directory would change, by collection.
     */
    private function getChanges(): array
    {
        $target_storage = $this->importStorageTransformer->transform($this->configStorageSync);
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
