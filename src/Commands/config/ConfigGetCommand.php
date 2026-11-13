<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Display a config value, or a whole configuration object.',
    aliases: ['cget', 'config-get'],
    usages: [
        'config:get system.site page.front',
        'config:get system.site',
        'config:get system.site --format=json',
    ],
)]
final class ConfigGetCommand
{
    use AutowireTrait;
    use ConfigNameTrait;

    public const string NAME = 'config:get';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
    ) {
    }

    /**
     * Prompts for the config name when it was not given.
     */
    #[Interact]
    public function askForName(InputInterface $input, OutputInterface $output): void
    {
        if (empty($input->getArgument('config_name'))) {
            $question = new Question((string) $this->t('Choose a configuration'));
            $question->setAutocompleterValues($this->configFactory->listAll());
            $input->setArgument('config_name', (new SymfonyStyle($input, $output))->askQuestion($question));
        }
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The config object name, for example <info>system.site</info>.', name: 'config_name', suggestedValues: [self::class, 'suggestConfigNames'])]
        string $configName,
        #[Argument('The config key, for example <info>page.front</info>. Optional.')]
        ?string $key = null,
        #[Option('The config storage source to read.')]
        string $source = 'active',
        #[Option('Apply module and settings.php overrides to values.')]
        ?bool $includeOverridden = null,
        #[Option('An alternative output format such as json. Defaults to yaml.')]
        ?OutputFormat $format = null,
    ): int {
        $this->requireConfig($configName);

        // Displaying overrides only applies to active storage.
        $config = $includeOverridden ? $this->configFactory->get($configName) : $this->configFactory->getEditable($configName);
        $value = $config->get($key ?? '');
        // A single value stays keyed by its origin, as it always was.
        $data = $key ? ["$configName:$key" => $value] : $value;

        $result = match ($format) {
            OutputFormat::Json => json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            default => Yaml::encode($data),
        };
        // Yaml::encode() ends with a newline of its own; writeln adds one.
        $output->writeln(rtrim($result, "\n"), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
