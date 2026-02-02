<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\SiteProcess;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Commands\core\RsyncCommand;
use Drush\Config\DrushConfig;
use Drush\Drush;
use Drush\Formatters\FormatterTrait;
use Drush\SiteAlias\ProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Export and transfer config from one environment to another.',
    aliases: ['cpull', 'config-pull'],
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\HelpLinks(links: [HelpLinks::Aliases, HelpLinks::ConfigExporting])]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
#[CLI\FieldLabels(labels: ['path' => 'Path'])]
final class ConfigPullCommand extends Command
{
    use AutowireTrait;
    use ConfigTrait;
    use FormatterTrait;

    const string NAME = 'config:pull';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly FormatterManager $formatterManager,
        private readonly LoggerInterface $logger,
        protected readonly ProcessManager $processManager,
        protected readonly DrushConfig $drushConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'source', description: 'A site-alias or the name of a subdirectory within /sites whose config you want to copy from.')
            ->addArgument(name: 'destination', description: 'A site-alias or the name of a subdirectory within /sites whose config you want to replace.')
            ->addOption(name: 'safe', description: 'Validate that there are no git uncommitted changes before proceeding')
            ->addOption(name: 'runner', mode: InputOption::VALUE_REQUIRED, description: 'Where to run the rsync command; defaults to the local site. Can also be <info>source</info> or <info>destination</info>.')
            ->addUsage(usage: 'config:pull @prod @stage')
            ->addUsage(usage: 'config:pull @prod @self:../config/sync');
    }

    public function execute(InputInterface $input, OutputInterface $output,): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $this->validateConfigPull($input);

        $global_options = Drush::redispatchOptions()  + ['strict' => 0];
        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');
        $sourceRecord = $this->siteAliasManager->get($source);

        $export_options = [
            // Use the standard backup directory on Destination.
            'destination' => true,
            'yes' => null,
            'format' => 'string',
        ];
        $this->logger->notice('Starting to export configuration on {source}.', ['source' => $source]);
        $process = $this->processManager->drush($sourceRecord, ConfigExportCommand::NAME, [], $export_options + $global_options);
        $process->mustRun();

        if ($this->drushConfig->simulate()) {
            $export_path = '/simulated/path';
        } elseif (empty(trim($process->getOutput()))) {
            throw new \Exception('The Drush config:export command did not report the path to the export directory.');
        } else {
            // Trailing slash ensures that we transfer files and not the containing dir.
            $export_path = trim($process->getOutput()) . '/';
        }

        if (!str_contains($destination, ':')) {
            $destination .= ':%config-sync';
        }
        $destinationHostPath = HostPath::create($this->siteAliasManager, $destination);

        if (!$runner = $input->getOption('runner')) {
            $destinationRecord = $destinationHostPath->getSiteAlias();
            $runner = $sourceRecord->isRemote() && $destinationRecord->isRemote() ? $destinationRecord : $this->siteAliasManager->getSelf();
        }
        $this->logger
            ->notice('Starting to rsync configuration files from {source} to {dest}.', [
                'source' => "$source:$export_path",
                'dest' => $destinationHostPath->getOriginal(),
            ]);
        $args = ["$source:$export_path", $destinationHostPath->getOriginal()];
        $options_double_dash = [
            'remove-source-files' => true,
            'delete' => true,
            'exclude' => '.htaccess',
        ];
        $process = $this->processManager->drush($runner, RsyncCommand::NAME, $args, ['yes' => true] + $global_options, $options_double_dash);
        $process->mustRun();
        return new PropertyList(['path' => $destinationHostPath->getOriginal()]);
    }

    public function validateConfigPull(InputInterface $input): void
    {
        if ($input->getOption('safe')) {
            $destinationRecord = $this->siteAliasManager->get($input->getArgument('destination'));
            /** @var SiteProcess $process */
            $process = $this->processManager->siteProcess($destinationRecord, ['git', 'diff', '--quiet']);
            $process->chdirToSiteRoot();
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception('There are uncommitted changes in your git working copy.');
            }
        }
    }
}
