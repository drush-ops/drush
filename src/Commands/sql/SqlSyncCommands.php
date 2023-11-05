<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Commands\core\CoreCommands;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\core\RsyncCommands;
use Drush\Commands\core\StatusCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Symfony\Component\Filesystem\Path;

final class SqlSyncCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    const SYNC = 'sql:sync';

    /**
     * Copy DB data from a source site to a target site. Transfers data via rsync.
     */
    #[CLI\Command(name: self::SYNC, aliases: ['sql-sync'])]
    #[CLI\Argument(name: 'source', description: 'A site-alias or site specification whose database you want to copy from.')]
    #[CLI\Argument(name: 'target', description: 'A site-alias or site specification whose database you want to replace.')]
    #[CLI\OptionsetTableSelection]
    #[CLI\Option(name: 'no-dump', description: 'Do not dump the sql database; always use an existing dump file.')]
    #[CLI\Option(name: 'no-sync', description: 'Do not rsync the database dump file from source to target.')]
    #[CLI\Option(name: 'runner', description: 'Where to run the rsync command; defaults to the local site. Can also be <info>source</info> or <info>target</info>.')]
    #[CLI\Option(name: 'create-db', description: 'Create a new database before importing the database dump on the target machine.')]
    #[CLI\Option(name: 'db-su', description: 'Account to use when creating a new database (e.g. <info>root</info>).')]
    #[CLI\Option(name: 'db-su-pw', description: 'Password for the db-su account.')]
    #[CLI\Option(name: 'source-dump', description: 'The path for retrieving the sql-dump on source machine.')]
    #[CLI\Option(name: 'target-dump', description: 'The path for storing the sql-dump on target machine.')]
    #[CLI\Option(name: 'extra-dump', description: 'Add custom arguments/options to the dumping of the database (e.g. mysqldump command).')]
    #[CLI\Usage(name: 'drush sql:sync @source @self', description: "Copy the database from the site with the alias 'source' to the local site.")]
    #[CLI\Usage(name: 'drush sql:sync @self @target', description: "Copy the database from the local site to the site with the alias 'target'.")]
    #[CLI\Usage(name: 'drush sql:sync #prod #dev', description: 'Copy the database from the site in /sites/prod to the site in /sites/dev (multisite installation).')]
    #[CLI\Topics(topics: [DocsCommands::ALIASES, DocsCommands::POLICY, DocsCommands::CONFIGURATION, DocsCommands::EXAMPLE_SYNC_VIA_HTTP])]
    public function sqlsync($source, $target, $options = ['no-dump' => false, 'no-sync' => false, 'runner' => self::REQ, 'create-db' => false, 'db-su' => self::REQ, 'db-su-pw' => self::REQ, 'target-dump' => self::REQ, 'source-dump' => self::OPT, 'extra-dump' => self::REQ]): void
    {
        $manager = $this->siteAliasManager();
        $sourceRecord = $manager->get($source);
        $targetRecord = $manager->get($target);

        // Append --strict in case we are calling older versions of Drush.
        $global_options = Drush::redispatchOptions()  + ['strict' => 0];

        // Create target DB if needed.
        if ($options['create-db']) {
            $this->logger()->notice(dt('Starting to create database on target.'));
            $process = $this->processManager()->drush($targetRecord, SqlCommands::CREATE, [], $global_options);
            $process->mustRun();
        }

        $source_dump_path = $this->dump($options, $global_options, $sourceRecord);

        $target_dump_path = $this->rsync($options, $sourceRecord, $targetRecord, $source_dump_path);

        $this->import($global_options, $target_dump_path, $targetRecord);
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::SYNC)]
    public function validate(CommandData $commandData): void
    {
        $source = $commandData->input()->getArgument('source');
        $target = $commandData->input()->getArgument('target');
        // Get target info for confirmation prompt.
        $manager = $this->siteAliasManager();
        if (!$sourceRecord = $manager->get($source)) {
            throw new \Exception(dt('Error: no alias record could be found for source !source', ['!source' => $source]));
        }
        if (!$targetRecord = $manager->get($target)) {
            throw new \Exception(dt('Error: no alias record could be found for target !target', ['!target' => $target]));
        }
        if (!$commandData->input()->getOption('no-dump') && !$source_db_name = $this->databaseName($sourceRecord)) {
            throw new \Exception(dt('Error: no database record could be found for source !source', ['!source' => $source]));
        }
        if (!$target_db_name = $this->databaseName($targetRecord)) {
            throw new \Exception(dt('Error: no database record could be found for target !target', ['!target' => $target]));
        }
        $txt_source = ($sourceRecord->remoteHost() ? $sourceRecord->remoteHost() . '/' : '') . $this->databaseName($sourceRecord);
        $txt_target = ($targetRecord->remoteHost() ? $targetRecord->remoteHost() . '/' : '') . $target_db_name;

        if ($commandData->input()->getOption('no-dump') && !$commandData->input()->getOption('source-dump')) {
            throw new \Exception(dt('The --source-dump option must be supplied when --no-dump is specified.'));
        }

        if ($commandData->input()->getOption('no-sync') && !$commandData->input()->getOption('target-dump')) {
            throw new \Exception(dt('The --target-dump option must be supplied when --no-sync is specified.'));
        }

        if (!$this->getConfig()->simulate()) {
            $this->output()->writeln(dt("You will destroy data in !target and replace with data from !source.", [
                '!source' => $txt_source,
                '!target' => $txt_target
            ]));
            if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
                throw new UserAbortException();
            }
        }
    }

    public function databaseName(SiteAlias $record): string
    {
        if ($this->processManager()->hasTransport($record) && $this->getConfig()->simulate()) {
            return 'simulated_db';
        }

        $process = $this->processManager()->drush($record, StatusCommands::STATUS, [], ['fields' => 'db-name', 'format' => 'json']);
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
            $this->logger()->notice(dt('Starting to dump database on source.'));
            $process = $this->processManager()->drush($sourceRecord, SqlCommands::DUMP, [], $dump_options + ['format' => 'json']);
            $process->mustRun();

            if ($this->getConfig()->simulate()) {
                $source_dump_path = '/simulated/path/to/dump.tgz';
            } else {
                $json = $process->getOutputAsJson();
                $source_dump_path = $json['path'];
            }
        } else {
            $source_dump_path = $options['source-dump'];
        }

        if (empty($source_dump_path)) {
            throw new \Exception(dt('The Drush sql:dump command did not report the path to the dump file.'));
        }
        return $source_dump_path;
    }

    /**
     * @param array $options
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
            $this->logger()->notice(dt('Starting to discover temporary files directory on target.'));
            $process = $this->processManager()->drush($targetRecord, StatusCommands::STATUS, [], ['format' => 'string', 'field' => 'drush-temp']);
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
                $runner = $sourceRecord->isRemote() && $targetRecord->isRemote() ? $targetRecord : $this->siteAliasManager()->getSelf();
            }
            if ($runner == 'source') {
                $runner = $sourceRecord;
            }
            if (($runner == 'target') || ($runner == 'destination')) {
                $runner = $targetRecord;
            }
            $this->logger()->notice(dt('Copying dump file from source to target.'));
            $process = $this->processManager()->drush($runner, RsyncCommands::RSYNC, [$sourceRecord->name() . ":$source_dump_path", $targetRecord->name() . ":$target_dump_path"], ['yes' => true], $double_dash_options);
            $process->mustRun($process->showRealtime());
        }
        return $target_dump_path;
    }

    /**
     * Import file into target.
     *
     * @param $global_options
     * @param $target_dump_path
     * @param $targetRecord
     */
    public function import($global_options, $target_dump_path, $targetRecord): void
    {
        $this->logger()->notice(dt('Starting to import dump file onto target database.'));
        $query_options = $global_options + [
            'file' => $target_dump_path,
            'file-delete' => true,
        ];
        $process = $this->processManager()->drush($targetRecord, SqlCommands::QUERY, [], $query_options);
        $process->mustRun();
    }
}
