<?php

declare(strict_types=1);

namespace Drush\Commands\core\deploy;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBoot;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\core\cache\CacheRebuildCommand;
use Drush\Commands\core\cache\CacheWarmCommand;
use Drush\Commands\core\DeployHookCommands;
use Drush\Commands\core\UpdateDBCommands;
use Drush\Drush;
use Drush\Formatters\FormatterTrait;
use Drush\SiteAlias\ProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Run several commands after performing a code deployment.',
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\Version(version: '10.3')]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
final class DeployCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'deploy';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        protected readonly FormatterManager $formatterManager,
        private readonly LoggerInterface $logger,
        private readonly ProcessManager $processManager,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $self = $this->siteAliasManager->getSelf();
        $redispatchOptions = Drush::redispatchOptions();
        $this->logger->notice("Database updates start.");
        $process = $this->processManager->drush($self, UpdateDBCommands::UPDATEDB, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->logger->notice("Config import start.");
        $process = $this->processManager->drush($self, ConfigImportCommands::IMPORT, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($this->processManager, $self, $redispatchOptions);

        $this->logger->notice("Deploy hook start.");
        $process = $this->processManager->drush($self, DeployHookCommands::HOOK, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        // Since this command is Bootstrap=None, we don't have access to the Drupal container.
        $boot_manager = Drush::bootstrapManager();
        $boot_object = Drush::bootstrap();
        if (($drupal_root = $boot_manager->getRoot()) && ($boot_object instanceof DrupalBoot && version_compare($boot_object->getVersion($drupal_root), '11.2-dev', '>='))) {
            $this->logger->notice("Cache prewarm start.");
            $process = $this->processManager->drush($self, CacheWarmCommand::NAME, [], $redispatchOptions);
            $process->mustRun($process->showRealtime());
        }

        return new PropertyList(['result' => 'Deploy completed successfully']);
    }

    public function cacheRebuild(ProcessManager $manager, SiteAlias $self, array $redispatchOptions): void
    {
        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger->notice("Cache rebuild start.");
        $process = $manager->drush($self, CacheRebuildCommand::NAME, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
