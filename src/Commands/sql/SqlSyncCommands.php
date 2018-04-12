<?php
namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\SiteAlias\AliasRecord;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;
use Symfony\Component\Config\Definition\Exception\Exception;
use Webmozart\PathUtil\Path;

class SqlSyncCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Copy DB data from a source site to a target site. Transfers data via rsync.
     *
     * @command sql:sync
     * @aliases sql-sync
     * @param $source A site-alias or the name of a subdirectory within /sites whose database you want to copy from.
     * @param $target A site-alias or the name of a subdirectory within /sites whose database you want to replace.
     * @optionset_table_selection
     * @option no-dump Do not dump the sql database; always use an existing dump file.
     * @option no-sync Do not rsync the database dump file from source to target.
     * @option runner Where to run the rsync command; defaults to the local site. Can also be 'source' or 'target'.
     * @option create-db Create a new database before importing the database dump on the target machine.
     * @option db-su Account to use when creating a new database (e.g. root).
     * @option db-su-pw Password for the db-su account.
     * @option source-dump The path for retrieving the sql-dump on source machine.
     * @option target-dump The path for storing the sql-dump on target machine.
     * @option extra-dump Add custom arguments/options to the dumping of the database (e.g. mysqldump command).
     * @usage drush sql:sync @source @self
     *   Copy the database from the site with the alias 'source' to the local site.
     * @usage drush sql:sync @self @target
     *   Copy the database from the local site to the site with the alias 'target'.
     * @usage drush sql:sync #prod #dev
     *   Copy the database from the site in /sites/prod to the site in /sites/dev (multisite installation).
     * @topics docs:aliases,docs:policy,docs:configuration,docs:example-sync-via-http
     * @throws \Exception
     */
    public function sqlsync($source, $target, $options = ['no-dump' => false, 'no-sync' => false, 'runner' => self::REQ, 'create-db' => false, 'db-su' => self::REQ, 'db-su-pw' => self::REQ, 'target-dump' => self::REQ, 'source-dump' => self::OPT])
    {
        $manager = $this->siteAliasManager();
        $sourceRecord = $manager->get($source);
        $targetRecord = $manager->get($target);

        $backend_options = [];
        $global_options = Drush::redispatchOptions()  + ['strict' => 0];

        // Create target DB if needed.
        if ($options['create-db']) {
            $this->logger()->notice(dt('Starting to create database on target.'));
            $return = drush_invoke_process($target, 'sql-create', [], $global_options, $backend_options);
            if ($return['error_status']) {
                throw new \Exception(dt('sql-create failed.'));
            }
        }

        $source_dump_path = $this->dump($options, $global_options, $sourceRecord, $backend_options);

        $target_dump_path = $this->rsync($options, $backend_options, $sourceRecord, $targetRecord, $source_dump_path);

        $this->import($global_options, $target_dump_path, $targetRecord, $backend_options);
    }

    /**
     * @hook validate sql-sync
     * @throws \Exception
     */
    public function validate(CommandData $commandData)
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
        if (!$source_db_name = $this->databaseName($sourceRecord)) {
            throw new \Exception(dt('Error: no database record could be found for source !source', ['!source' => $source]));
        }
        if (!$target_db_name = $this->databaseName($targetRecord)) {
            throw new \Exception(dt('Error: no database record could be found for target !target', ['!target' => $target]));
        }
        $txt_source = ($sourceRecord->remoteHost() ? $sourceRecord->remoteHost() . '/' : '') . $source_db_name;
        $txt_target = ($targetRecord->remoteHost() ? $targetRecord->remoteHost() . '/' : '') . $target_db_name;

        if ($commandData->input()->getOption('no-dump') && !$commandData->input()->getOption('source-dump')) {
            throw new \Exception(dt('The --source-dump option must be supplied when --no-dump is specified.'));
        }

        if ($commandData->input()->getOption('no-sync') && !$commandData->input()->getOption('target-dump')) {
            throw new \Exception(dt('The --target-dump option must be supplied when --no-sync is specified.'));
        }

        if (!Drush::simulate()) {
            $this->output()->writeln(dt("You will destroy data in !target and replace with data from !source.", [
                '!source' => $txt_source,
                '!target' => $txt_target
            ]));
            if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
                throw new UserAbortException();
            }
        }
    }

    public function databaseName(AliasRecord $record)
    {
        if ($record->isRemote() && preg_match('#\.simulated$#', $record->remoteHost())) {
            return 'simulated_db';
        }
        $values = drush_invoke_process($record, "core-status", [], [], ['integrate' => false, 'override-simulated' => true]);
        if (is_array($values) && ($values['error_status'] == 0)) {
            return $values['object']['db-name'];
        }
    }

    /**
     * Perform sql-dump on source unless told otherwise.
     *
     * @param $options
     * @param $global_options
     * @param $sourceRecord
     * @param $backend_options
     *
     * @return string
     *   Path to the source dump file.
     * @throws \Exception
     */
    public function dump($options, $global_options, $sourceRecord, $backend_options)
    {
        $dump_options = $global_options + [
            'gzip' => true,
            'result-file' => $options['source-dump'] ?: 'auto',
        ];
        if (!$options['no-dump']) {
            $this->logger()->notice(dt('Starting to dump database on source.'));
            $return = drush_invoke_process($sourceRecord, 'sql-dump', [], $dump_options, $backend_options);
            if ($return['error_status']) {
                throw new \Exception(dt('sql-dump failed.'));
            } elseif (Drush::simulate()) {
                $source_dump_path = '/simulated/path/to/dump.tgz';
            } else {
                $source_dump_path = $return['object'];
                if (!is_string($source_dump_path)) {
                    throw new \Exception(dt('The Drush sql-dump command did not report the path to the dump file produced.  Try upgrading the version of Drush you are using on the source machine.'));
                }
            }
        } else {
            $source_dump_path = $options['source-dump'];
        }
        return $source_dump_path;
    }

    /**
     * @param array $options
     * @param array $backend_options
     * @param AliasRecord $sourceRecord
     * @param AliasRecord $targetRecord
     * @param $source_dump_path
     * @return string
     *   Path to the target file.
     * @throws \Exception
     */
    public function rsync($options, $backend_options, AliasRecord $sourceRecord, AliasRecord $targetRecord, $source_dump_path)
    {
        $do_rsync = !$options['no-sync'];
        // Determine path/to/dump on target.
        if ($options['target-dump']) {
            $target_dump_path = $options['target-dump'];
            $backend_options['interactive'] = false;  // @temporary: See https://github.com/drush-ops/drush/pull/555
        } elseif (!$sourceRecord->isRemote() && !$targetRecord->isRemote()) {
            $target_dump_path = $source_dump_path;
            $do_rsync = false;
        } else {
            $tmp = '/tmp'; // Our fallback plan.
            $this->logger()->notice(dt('Starting to discover temporary files directory on target.'));
            $return = drush_invoke_process($targetRecord, 'core-status', [], [], ['integrate' => false, 'override-simulated' => true]);
            if (!$return['error_status'] && isset($return['object']['drush-temp'])) {
                $tmp = $return['object']['drush-temp'];
            }
            $target_dump_path = Path::join($tmp, basename($source_dump_path));
            $backend_options['interactive'] = false;  // No need to prompt as target is a tmp file.
        }

        if ($do_rsync) {
            $rsync_options = [];
            if (!$options['no-dump']) {
                // Cleanup if this command created the dump file.
                $rsync_options[] = '--remove-source-files';
            }
            if (!$runner = $options['runner']) {
                $runner = $sourceRecord->isRemote() && $targetRecord->isRemote() ? $targetRecord : '@self';
            }
            if ($runner == 'source') {
                $runner = $sourceRecord;
            }
            if (($runner == 'target') || ($runner == 'destination')) {
                $runner = $targetRecord;
            }
            // Since core-rsync is a strict-handling command and drush_invoke_process() puts options at end, we can't send along cli options to rsync.
            // Alternatively, add options like ssh.options to a site alias (usually on the machine that initiates the sql-sync).
            $return = drush_invoke_process($runner, 'core-rsync', array_merge([$sourceRecord->name() . ":$source_dump_path", $targetRecord->name() . ":$target_dump_path", '--'], $rsync_options), [], $backend_options);
            $this->logger()->notice(dt('Copying dump file from source to target.'));
            if ($return['error_status']) {
                throw new \Exception(dt('core-rsync failed.'));
            }
        }
        return $target_dump_path;
    }

    /**
     * Import file into target.
     *
     * @param $global_options
     * @param $target_dump_path
     * @param $targetRecord
     * @param $backend_options
     */
    public function import($global_options, $target_dump_path, $targetRecord, $backend_options)
    {
        $this->logger()->notice(dt('Starting to import dump file onto target database.'));
        $query_options = $global_options + [
            'file' => $target_dump_path,
            'file-delete' => true,
        ];
        $return = drush_invoke_process($targetRecord, 'sql-query', [], $query_options, $backend_options);
        if ($return['error_status']) {
            throw new Exception(dt('Failed to import !dump into target.', ['!dump' => $target_dump_path]));
        }
    }
}
