<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\config;

use Drupal\Core\Command\Exception\UserAbortException;
use Drupal\Core\Config\ConfigDirectoryNotDefinedException;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageCopyTrait;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\SyncFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A copy of core's \Drupal\Core\Config\Command\ConfigExportCommand (core
 * commit e1b2512322), registered with dr only on core versions that do not
 * have that command. Remove it once they all do.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Export configuration.',
)]
final class ConfigExportCommand
{
    use StringTranslationTrait;
    use StorageCopyTrait;
    use ConfigCommandTrait;

    public const string NAME = 'config:export';

    public function __construct(
        #[Autowire(service: 'config.storage.export')]
        private readonly StorageInterface $export,
        private readonly SyncFactory $syncFactory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Answer the confirmation question with yes.', shortcut: 'y')]
        bool $yes = false,
    ): int {
        // Resolve the sync storage here rather than in the constructor: the
        // factory throws when no sync directory is configured, and a throwing
        // constructor would break `dr list` for every command.
        try {
            $sync = $this->syncFactory->get();
        } catch (ConfigDirectoryNotDefinedException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $comparer = new StorageComparer($this->export, $sync);
        $comparer->createChangelist();

        if (!$comparer->hasChanges()) {
            $io->success((string) $this->t('The active configuration to export matches the configuration in the sync directory.'));
            return Command::SUCCESS;
        }

        $this->renderTable($comparer, $io);

        if (!$yes && !$io->confirm((string) $this->t('Do you want to export the configuration?'))) {
            throw new UserAbortException('Cancelled');
        }

        self::replaceStorageContents($this->export, $sync);

        $io->success((string) $this->t('Configuration successfully exported'));

        return Command::SUCCESS;
    }
}
