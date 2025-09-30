<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Exec\ExecTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: self::NAME,
    description: 'Export Drupal configuration to a directory.',
    aliases: ['cex', 'config-export'],
)]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
final class ConfigExportCommand extends Command
{
    use AutowireTrait;
    use ConfigTrait;
    use FormatterTrait;
    use ExecTrait;

    public const NAME = 'config:export';

    public function __construct(
        protected readonly ConfigManagerInterface $configManager,
        #[Autowire(service: 'config.storage.sync')]
        protected ?StorageInterface $configStorageSync,
        #[Autowire(service: 'config.storage.export')]
        protected ?StorageInterface $configStorageExport,
        #[Autowire(service: 'config.storage')]
        protected readonly StorageInterface $configStorage,
        protected readonly FormatterManager $formatterManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('add', null, InputOption::VALUE_NONE, 'Run `git add -p` after exporting. This lets you choose which config changes to sync for commit.')
            ->addOption('commit', null, InputOption::VALUE_NONE, 'Run `git add -A` and `git commit` after exporting.  This commits everything that was exported without prompting.')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Commit comment for the exported configuration.  Optional; may only be used with --commit.')
            ->addOption('destination', null, InputOption::VALUE_OPTIONAL, 'An arbitrary directory that should receive the exported files. A backup directory is used when no value is provided.')
            ->addOption('diff', null, InputOption::VALUE_NONE, 'Show preview as a diff, instead of a change list.')
            ->addUsage('config:export --destination')
            ->setHelp('Export configuration files to the site\'s config directory or export configuration and save files in a backup directory.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        // Get destination directory.
        $destination = $input->getOption('destination');
        if (is_null($destination) && str_contains(strval($input), '--destination')) {
            // Passing --destination with no value indicates that a new target should be created in the backups dir.
            // Historically, thats represented by a boolean true.
            $destination = true;
        }
        $destination_dir = $this->getDirectory($destination);

        // Validate destination
        $this->validateDestination($destination_dir);

        // Do the actual config export operation.
        $preview = $this->performExport($input, $output, $destination_dir);

        // Do the VCS operations.
        $this->performAddCommit($input, $destination_dir, $preview);

        return new PropertyList(['destination-dir' => $destination_dir]);
    }

    private function performExport(InputInterface $input, OutputInterface $output, string $destination_dir): string
    {
        $io = new DrushStyle($input, $output);
        $sync_directory = Settings::get('config_sync_directory');

        // Prepare the configuration storage for the export.
        if ($sync_directory !== null && $destination_dir == Path::canonicalize($sync_directory)) {
            $target_storage = $this->configStorageSync;
        } else {
            $target_storage = new FileStorage($destination_dir);
        }

        if (count(glob($destination_dir . '/*')) > 0) {
            // Retrieve a list of differences between the active and target configuration (if any).
            $config_comparer = new StorageComparer($this->configStorageExport, $target_storage);
            if (!$config_comparer->createChangelist()->hasChanges()) {
                $this->logger->notice(sprintf('The active configuration is identical to the configuration in the export directory (%s).', $destination_dir));
                return 'No changes to export.';
            }
            $preamble = "Differences of the active config to the export directory:\n";

            if ($input->getOption('diff')) {
                $diff = $this->getDiff($target_storage, $this->configStorageExport, $output);
                $this->logger->notice($preamble . $diff);
                $preview = $diff;
            } else {
                $change_list = [];
                foreach ($config_comparer->getAllCollectionNames() as $collection) {
                    $change_list[$collection] = $config_comparer->getChangelist(null, $collection);
                }
                // Print a table with changes in color, then re-generate again without
                // color to place in the commit comment.
                $bufferedOutput = new BufferedOutput();
                $table = $this->configChangesTable($change_list, $bufferedOutput, false);
                $table->render();
                $preview = $bufferedOutput->fetch();
                $this->logger->notice($preamble . $preview);
            }

            if (!$io->confirm(sprintf('The .yml files in your export directory will be deleted and replaced with the active config.'), hint: sprintf('Target: %s', $destination_dir))) {
                throw new \Exception('Export cancelled by user.');
            }

            // Only delete .yml files, and not .htaccess or .git.
            $target_storage->deleteAll();

            // Also delete collections.
            foreach ($target_storage->getAllCollectionNames() as $collection_name) {
                $target_collection = $target_storage->createCollection($collection_name);
                $target_collection->deleteAll();
            }
        } else {
            $preview = 'No existing configuration to diff against.';
        }

        // Write all .yml files.
        $this->copyConfig($this->configStorageExport, $target_storage);

        $io->success(sprintf('Configuration successfully exported to %s.', $destination_dir));
        return $preview;
    }

    private function performAddCommit(InputInterface $input, string $destination_dir, string $preview): void
    {
        // Commit or add exported configuration if requested.
        if ($input->getOption('commit')) {
            // There must be changed files at the destination dir; if there are not, then
            // we will skip the commit step.
            $process = new Process(['git', 'status', '--porcelain', '.'], $destination_dir);
            $process->mustRun();
            $uncommitted_changes = $process->getOutput();
            if (!empty($uncommitted_changes)) {
                $process = new Process(['git', 'add', '-A', '.'], $destination_dir);
                $process->mustRun();
                $message = $input->getOption('message') ?: 'Exported configuration.' . $preview;
                $comment_file = drush_save_data_to_temp_file($message);
                $process = new Process(['git', 'commit', "--file=$comment_file"], $destination_dir);
                $process->mustRun();
            }
        } elseif ($input->getOption('add')) {
            $process = new Process(['git', 'add', '-p', $destination_dir]);
            $process->run();
        }
    }

    private function validateDestination(?string $destination): void
    {
        if (!empty($destination)) {
            if (!file_exists($destination)) {
                $parent = dirname($destination);
                if (!is_dir($parent)) {
                    throw new \Exception('The destination parent directory does not exist.');
                }
                if (!is_writable($parent)) {
                    throw new \Exception('The destination parent directory is not writable.');
                }
            } else {
                if (!is_dir($destination)) {
                    throw new \Exception('The destination is not a directory.');
                }
                if (!is_writable($destination)) {
                    throw new \Exception('The destination directory is not writable.');
                }
            }
        }
    }
}
