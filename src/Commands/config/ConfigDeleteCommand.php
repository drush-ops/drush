<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
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
    description: 'Delete a configuration key, or a whole object(s).',
    aliases: ['cdel', 'config-delete'],
    usages: [
        'config:delete system.site,system.rss',
        'config:delete system.site page.front',
    ],
)]
final class ConfigDeleteCommand
{
    use AutowireTrait;
    use ConfigNameTrait;

    public const string NAME = 'config:delete';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
    ) {
    }

    /**
     * Prompts for the config name when it was not given.
     *
     * Not #[Ask] on the argument: that attribute cannot offer the config
     * names for autocompletion.
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
        #[Argument('The config object name(s). Delimit multiple with commas.', name: 'config_name', suggestedValues: [self::class, 'suggestConfigNames'])]
        string $configName,
        #[Argument('A config key to clear. May not be used with multiple config names.')]
        ?string $key = null,
    ): int {
        $names = StringUtils::csvToArray($configName);
        // Check every name before deleting anything.
        foreach ($names as $name) {
            $this->requireConfig($name);
        }

        if ($key) {
            if (count($names) > 1) {
                throw new \InvalidArgumentException((string) $this->t('A key may not be used with multiple config names.'));
            }
            $config = $this->configFactory->getEditable($names[0]);
            if ($config->get($key) === null) {
                throw new \InvalidArgumentException((string) $this->t('Configuration key @key not found.', ['@key' => $key]));
            }
            $config->clear($key)->save();
            return Command::SUCCESS;
        }

        foreach ($names as $name) {
            $this->configFactory->getEditable($name)->delete();
        }

        return Command::SUCCESS;
    }
}
