<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\SiteProcess;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\core\RsyncCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class ConfigPullCommands extends DrushCommands
{
    use AutowireTrait;

    const PULL = 'config:pull';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Export and transfer config from one environment to another.
     */
    #[CLI\Command(name: self::PULL, aliases: ['cpull', 'config-pull'])]
    #[CLI\Argument(name: 'source', description: 'A site-alias or the name of a subdirectory within /sites whose config you want to copy from.')]
    #[CLI\Argument(name: 'destination', description: 'A site-alias or the name of a subdirectory within /sites whose config you want to replace.')]
    #[CLI\Option(name: 'safe', description: 'Validate that there are no git uncommitted changes before proceeding')]
    #[CLI\Option(name: 'runner', description: 'Where to run the rsync command; defaults to the local site. Can also be <info>source</info> or <info>destination</info>.')]
    #[CLI\Usage(name: 'drush config:pull @prod @stage', description: 'Export config from @prod and transfer to @stage.')]
    #[CLI\Usage(name: 'drush config:pull @prod @self:../config/sync', description: 'Export config and transfer to a custom directory. Relative paths are calculated from Drupal root.')]
    #[CLI\Topics(topics: [DocsCommands::ALIASES, DocsCommands::CONFIG_EXPORTING])]
    #[CLI\FieldLabels(labels: ['path' => 'Path'])]
    public function pull(string $source, string $destination, array $options = ['safe' => false, 'runner' => null, 'format' => 'null']): PropertyList
    {
        $global_options = Drush::redispatchOptions()  + ['strict' => 0];
        $sourceRecord = $this->siteAliasManager->get($source);

        $export_options = [
            // Use the standard backup directory on Destination.
            'destination' => true,
            'yes' => null,
            'format' => 'string',
        ];
        $this->logger()->notice(dt('Starting to export configuration on :source.', [':source' => $source]));
        $process = $this->processManager()->drush($sourceRecord, ConfigExportCommands::EXPORT, [], $export_options + $global_options);
        $process->mustRun();

        if ($this->getConfig()->simulate()) {
            $export_path = '/simulated/path';
        } elseif (empty(trim($process->getOutput()))) {
            throw new \Exception(dt('The Drush config:export command did not report the path to the export directory.'));
        } else {
            // Trailing slash ensures that we transfer files and not the containing dir.
            $export_path = trim($process->getOutput()) . '/';
        }

        if (!str_contains($destination, ':')) {
            $destination .= ':%config-sync';
        }
        $destinationHostPath = HostPath::create($this->siteAliasManager, $destination);

        if (!$runner = $options['runner']) {
            $destinationRecord = $destinationHostPath->getSiteAlias();
            $runner = $sourceRecord->isRemote() && $destinationRecord->isRemote() ? $destinationRecord : $this->siteAliasManager->getSelf();
        }
        $this->logger()
          ->notice(dt('Starting to rsync configuration files from !source to !dest.', [
              '!source' => "$source:$export_path",
              '!dest' => $destinationHostPath->getOriginal(),
          ]));
        $args = ["$source:$export_path", $destinationHostPath->getOriginal()];
        $options_double_dash = [
            'remove-source-files' => true,
            'delete' => true,
            'exclude' => '.htaccess',
        ];
        $process = $this->processManager()->drush($runner, RsyncCommands::RSYNC, $args, ['yes' => true, 'debug' => true], $options_double_dash);
        $process->mustRun();
        return new PropertyList(['path' => $destinationHostPath->getOriginal()]);
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::PULL)]
    public function validateConfigPull(CommandData $commandData): void
    {
        if ($commandData->input()->getOption('safe')) {
            $destinationRecord = $this->siteAliasManager->get($commandData->input()->getArgument('destination'));
            /** @var SiteProcess $process */
            $process = $this->processManager()->siteProcess($destinationRecord, ['git', 'diff', '--quiet']);
            $process->chdirToSiteRoot();
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception('There are uncommitted changes in your git working copy.');
            }
        }
    }
}
