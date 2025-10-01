<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\Util\Escape;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Open a config file in a text editor. Edits are imported after closing editor.',
    aliases: ['cedit', 'config-edit'],
)]
#[CLI\OptionsetGetEditor()]
#[CLI\ValidateModulesEnabled(modules: ['config'])]
#[CLI\ValidateConfigName()]
final class ConfigEditCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    const NAME = 'config:edit';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected readonly ProcessManager $processManager,
        protected readonly SiteAliasManagerInterface $siteAliasManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'config_name', description: 'The config object name, for example <info>system.site</info>.')
            ->addUsage('drush config:edit image.style.large')
            ->addUsage('drush config:edit')
            ->addUsage('drush --bg config-edit image.style.large');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if ($input->hasArgument('config_name') && empty($input->getArgument('config_name'))) {
            $io = new DrushStyle($input, $output);
            // Classes using this trait must have a $configFactory property.
            $config_names = $this->configFactory->listAll();
            $choice = $io->suggest('Choose a configuration', array_combine($config_names, $config_names), scroll: 200, required: true);
            $input->setArgument('config_name', $choice);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config_name = $input->getArgument('config_name');
        $config = $this->configFactory->get($config_name);
        $active_storage = $config->getStorage();
        $contents = $active_storage->read($config_name);

        // Write tmp YAML file for editing
        $temp_dir = drush_tempdir();
        $temp_storage = new FileStorage($temp_dir);
        $temp_storage->write($config_name, $contents);

        // Note that `getEditor()` returns a string that contains a
        // %s placeholder for the config file path.
        $exec = self::getEditor($input->getOption('editor'));
        $cmd = sprintf($exec, Escape::shellArg($temp_storage->getFilePath($config_name)));
        $process = $this->processManager->shell($cmd);
        $process->setTty(true);
        $process->mustRun();

        // Perform import operation if user did not immediately exit editor.
        if (!$input->getOption('bg')) {
            $redispatch_options = Drush::redispatchOptions() + ['strict' => 0, 'partial' => true, 'source' => $temp_dir];
            $self = $this->siteAliasManager->getSelf();
            $process = $this->processManager->drush($self, ConfigImportCommands::IMPORT, [], $redispatch_options);
            $process->mustRun($process->showRealtime());
        }
        return Command::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('config_name')) {
            $suggestions->suggestValues($this->configFactory->listAll());
        }
    }
}
