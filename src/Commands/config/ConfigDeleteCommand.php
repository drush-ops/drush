<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Delete a configuration key, or a whole object(s).',
    aliases: ['cdel', 'config-delete'],
)]
#[CLI\ValidateConfigName()]
final class ConfigDeleteCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'config:delete';

    public function __construct(
        protected readonly ConfigFactoryInterface $configFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('config_name', InputArgument::REQUIRED, 'The config object name(s). Delimit multiple with commas.')
            ->addArgument('key', InputArgument::OPTIONAL, 'A config key to clear, May not be used with multiple config names.')
            ->addUsage('config:delete system.site,system.rss')
            ->addUsage("config:delete system.site page.front");
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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $config_name = $input->getArgument('config_name');
        $key = $input->getArgument('key');

        if ($key) {
            $config = $this->configFactory->getEditable($config_name);
            if ($config->get($key) === null) {
                $output->writeln(sprintf('<error>Configuration key %s not found.</error>', $key));
                return Command::FAILURE;
            }
            $config->clear($key)->save();
        } else {
            $names = StringUtils::csvToArray($config_name);
            foreach ($names as $name) {
                // Validate that config exists before attempting to delete
                $config = $this->configFactory->get($name);
                if ($config->isNew()) {
                    $output->writeln(sprintf('<error>Config %s does not exist</error>', $name));
                    return Command::FAILURE;
                }

                $editable_config = $this->configFactory->getEditable($name);
                $editable_config->delete();
            }
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
