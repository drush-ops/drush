<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\SiteAlias\SiteAliasInterface;
use Drush\Config\DrushConfig;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Commands\core\RsyncCommands;
use Drush\Commands\core\StatusCommand;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: self::NAME,
    description: 'Copy DB data from a source site to a target site. Transfers data via rsync.',
    aliases: ['sql-sync'],
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\OptionsetTableSelection]
#[CLI\HelpLinks(links: [HelpLinks::Aliases, HelpLinks::Policy, HelpLinks::DrushConfiguration, HelpLinks::SyncViaHttp])]
final class SqlSyncCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'sql:sync';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly LoggerInterface $logger,
        private readonly ProcessManager $processManager,
        protected readonly DrushConfig $drushConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'A site-alias or site specification whose database you want to copy from.')
            ->addArgument('target', InputArgument::REQUIRED, 'A site-alias or site specification whose database you want to replace.')
            ->addOption('no-dump', null, InputOption::VALUE_NONE, 'Do not dump the sql database; always use an existing dump file.')
            ->addOption('no-sync', null, InputOption::VALUE_NONE, 'Do not rsync the database dump file from source to target.')
            ->addOption('runner', null, InputOption::VALUE_REQUIRED, 'Where to run the rsync command; defaults to the local site. Can also be source or target.')
            ->addOption('create-db', null, InputOption::VALUE_NONE, 'Create a new database before importing the database dump on the target machine.')
            ->addOption('db-su', null, InputOption::VALUE_REQUIRED, 'Account to use when creating a new database (e.g. root).')
            ->addOption('db-su-pw', null, InputOption::VALUE_REQUIRED, 'Password for the db-su account.')
            ->addOption('source-dump', null, InputOption::VALUE_OPTIONAL, 'The path for retrieving the sql-dump on source machine.')
            ->addOption('target-dump', null, InputOption::VALUE_REQUIRED, 'The path for storing the sql-dump on target machine.')
            ->addOption('extra-dump', null, InputOption::VALUE_REQUIRED, 'Add custom arguments/options to the dumping of the database (e.g. mysqldump command).')
            ->addUsage('sql:sync @source @self')
            ->addUsage('sql:sync @self @target')
            ->addUsage('sql:sync #prod #dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $this->validateSync($input, $output, $io);

        $source = $input->getArgument('source');
        $target = $input->getArgument('target');
        $options = $input->getOptions();

        $sourceRecord = $this->siteAliasManager->get($source);
        $targetRecord = $this->siteAliasManager->get($target);

        // Append --strict in case we are calling older versions of Drush.
        $global_options = Drush::redispatchOptions()  + ['strict' => 0];

        // Create target DB if needed.
        if ($options['create-db']) {
            $this->logger->notice('Starting to create database on target.');
            $process = $this->processManager->drush($targetRecord, SqlCommands::CREATE, [], $global_options);
            $process->mustRun();
        }

        $source_dump_path = $this->dump($options, $global_options, $sourceRecord);

        $target_dump_path = $this->rsync($options, $sourceRecord, $targetRecord, $source_dump_path);

        $this->import($global_options, $target_dump_path, $targetRecord);

        return self::SUCCESS;
    }

    public function validateSync(InputInterface $input, OutputInterface $output, DrushStyle $io): void
    {
        $source = $input->getArgument('source');
        $target = $input->getArgument('target');
        // Get target info for confirmation prompt.
        if (!$sourceRecord = $this->siteAliasManager->get($source)) {
            throw new \Exception(sprintf('Error: no alias record could be found for source %s', $source));
        }
        if (!$targetRecord = $this->siteAliasManager->get($target)) {
            throw new \Exception(sprintf('Error: no alias record could be found for target %s', $target));
        }
        if (!$input->getOption('no-dump') && !$source_db_name = $this->databaseName($sourceRecord)) {
            throw new \Exception(sprintf('Error: no database record could be found for source %s', $source));
        }
        if (!$target_db_name = $this->databaseName($targetRecord)) {
            throw new \Exception(sprintf('Error: no database record could be found for target %s', $target));
        }
        $txt_source = ($sourceRecord->remoteHost() ? $sourceRecord->remoteHost() . '/' : '') . $this->databaseName($sourceRecord);
        $txt_target = ($targetRecord->remoteHost() ? $targetRecord->remoteHost() . '/' : '') . $target_db_name;

        if ($input->getOption('no-dump') && !$input->getOption('source-dump')) {
            throw new \Exception('The --source-dump option must be supplied when --no-dump is specified.');
        }

        if ($input->getOption('no-sync') && !$input->getOption('target-dump')) {
            throw new \Exception('The --target-dump option must be supplied when --no-sync is specified.');
        }

        if (!$this->drushConfig->simulate()) {
            $output->writeln(sprintf("You will destroy data in %s and replace with data from %s.", $txt_target, $txt_source));
            if (!$io->confirm('Do you really want to continue?')) {
                throw new UserAbortException();
            }
        }
    }

    public function databaseName(SiteAlias $record): string
    {
        if ($this->processManager->hasTransport($record) && $this->drushConfig->simulate()) {
            return 'simulated_db';
        }

        $process = $this->processManager->drush($record, StatusCommand::NAME, [], ['fields' => 'db-name', 'format' => 'json'] + Drush::redispatchOptions() + ['strict' => 0]);
        $process->setSimulated(false);
        $process->mustRun();
        $data = $process->getOutputAsJson();
        if (!isset($data['db-name'])) {
            throw new \Exception('Could not look up database name for ' . $record->name());
        }
        return trim($data['db-name']);
    }

    /**
     * Perform sql-dump on source unless told otherwise. Returns the path to the dump file.
     */
    public function dump(array $options, array $global_options, SiteAlias $sourceRecord): string
    {
        $dump_options = $global_options + [
                'gzip' => true,
                'result-file' => $options['source-dump'] ?: 'auto',
            ];
        if (!$options['no-dump']) {
            $this->logger->notice('Starting to dump database on source.');
            $process = $this->processManager->drush($sourceRecord, SqlCommands::DUMP, [], $dump_options + ['format' => 'json'] + Drush::redispatchOptions() + ['strict' => 0]);
            $process->mustRun();

            if ($this->drushConfig->simulate()) {
                $source_dump_path = '/simulated/path/to/dump.tgz';
            } else {
                $json = $process->getOutputAsJson();
                $source_dump_path = $json['path'];
            }
        } else {
            $source_dump_path = $options['source-dump'];
        }

        if (empty($source_dump_path)) {
            throw new \Exception('The Drush sql:dump command did not report the path to the dump file.');
        }
        return $source_dump_path;
    }

    /**
     * @param $source_dump_path
     *   Path to the target file.
     * @throws \Exception
     */
    public function rsync(array $options, SiteAlias $sourceRecord, SiteAlias $targetRecord, $source_dump_path): string
    {
        $do_rsync = !$options['no-sync'];
        // Determine path/to/dump on target.
        if ($options['target-dump']) {
            $target_dump_path = $options['target-dump'];
        } elseif (!$sourceRecord->isRemote() && !$targetRecord->isRemote()) {
            $target_dump_path = $source_dump_path;
            $do_rsync = false;
        } else {
            $tmp = '/tmp'; // Our fallback plan.
            $this->logger->notice('Starting to discover temporary files directory on target.');
            $process = $this->processManager->drush($targetRecord, StatusCommand::NAME, [], ['format' => 'string', 'field' => 'drush-temp'] + Drush::redispatchOptions() + ['strict' => 0]);
            $process->setSimulated(false);
            $process->run();

            if ($process->isSuccessful()) {
                $tmp = trim($process->getOutput());
            }
            $target_dump_path = Path::join($tmp, basename($source_dump_path));
        }

        if ($do_rsync) {
            $double_dash_options = [];
            if (!$options['no-dump']) {
                // Cleanup if this command created the dump file.
                $double_dash_options['remove-source-files'] = true;
            }
            if (!$runner = $options['runner']) {
                $runner = $sourceRecord->isRemote() && $targetRecord->isRemote() ? $targetRecord : $this->siteAliasManager->getSelf();
            }
            if ($runner == 'source') {
                $runner = $sourceRecord;
            }
            if (($runner == 'target') || ($runner == 'destination')) {
                $runner = $targetRecord;
            }
            $this->logger->notice('Copying dump file from source to target.');
            $process = $this->processManager->drush($runner, RsyncCommands::RSYNC, [$sourceRecord->name() . ":$source_dump_path", $targetRecord->name() . ":$target_dump_path"], ['yes' => true] + Drush::redispatchOptions() + ['strict' => 0], $double_dash_options);
            $process->mustRun($process->showRealtime());
        }
        return $target_dump_path;
    }

    /**
     * Import file into target.
     */
    public function import($global_options, $target_dump_path, SiteAliasInterface $targetRecord): void
    {
        $this->logger->notice('Starting to import dump file onto target database.');
        $query_options = $global_options + [
            'file' => $target_dump_path,
            'file-delete' => true,
        ];
        $process = $this->processManager->drush($targetRecord, SqlCommands::QUERY, [], $query_options + Drush::redispatchOptions() + ['strict' => 0]);
        $process->mustRun();
    }
}
