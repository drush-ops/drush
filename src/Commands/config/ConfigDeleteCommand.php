<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Command\Command;

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

    public function __invoke(
        #[Ask('Choose a configuration')]
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
