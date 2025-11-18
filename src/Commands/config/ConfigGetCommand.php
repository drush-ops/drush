<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display a config value, or a whole configuration object.',
    aliases: ['cget','config-get']
)]
#[CLI\Formatter(returnType: UnstructuredListData::class, defaultFormatter: 'yaml')]
#[CLI\ValidateConfigName()]
final class ConfigGetCommand extends Command
{
    use AutowireTrait;
    use ConfigNameTrait;
    use FormatterTrait;

    public const string NAME = 'config:get';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->addArgument('config_name', InputArgument::REQUIRED, 'The config object name, for example <info>system.site</info>.')
            ->addArgument('key', InputArgument::OPTIONAL, 'The config key, for example <info>page.front</info>. Optional')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'The config storage source to read.', 'active')
            ->addOption('include-overridden', null, InputOption::VALUE_NEGATABLE, 'Apply module and settings.php overrides to values')
            ->addUsage('config:get system.site page.front')
            ->addUsage('config:get system.site');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('config_name') && empty($input->getArgument('config_name'))) {
            $io = new DrushStyle($input, $output);
            // Classes using this trait must have a $configFactory property.
            $config_names = $this->configFactory->listAll();
            $choice = $io->suggest('Choose a configuration', array_combine($config_names, $config_names), scroll: 200, required: true);
            $input->setArgument('config_name', $choice);
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    protected function doExecute($input): string|array
    {
        $config_name = $input->getArgument('config_name');
        // $this->validateConfigName($config_name);
        $key = $input->getArgument('key');

        // Displaying overrides only applies to active storage.
        $config = $input->getOption('include-overridden') ? $this->configFactory->get($config_name) : $this->configFactory->getEditable($config_name);
        $value = $config->get($key);
        return $key ? ["$config_name:$key" => $value] : $value;
    }
}
