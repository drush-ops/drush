<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Save a config value directly. Does not perform a config import.',
    aliases: ['cset', 'config-set'],
    usages: [
        'config:set system.site name MySite',
        'config:set user.role.anonymous permissions \'[]\'',
        'config:set system.site name \'NULL\'',
        'config:set system.performance css.preprocess false',
        "config:set --input-format=yaml system.site page {403: '403', front: home}",
        'config:set --input-format=yaml user.role.authenticated permissions [foo,bar]',
        'config:set --input-format=yaml user.role.authenticated ? "{label: \'Auth user\', weight: 5}"',
        'cat tmp.yml | drush config:set --input-format=yaml user.mail ? -',
    ],
)]
final class ConfigSetCommand
{
    use AutowireTrait;
    use ConfigNameTrait;

    public const string NAME = 'config:set';

    /**
     * Accepted values of --input-format.
     */
    public const array INPUT_FORMATS = ['string', 'yaml'];

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The config object name, for example <info>system.site</info>.', name: 'config_name', suggestedValues: [self::class, 'suggestConfigNames'])]
        string $configName,
        #[Argument('The config key, for example <info>page.front</info>. Use <info>?</info> if you are updating multiple top-level keys.')]
        string $key,
        #[Argument('The value to assign to the config key. The words <info>true</info>, <info>false</info> and <info>null</info> are saved as those typed values, not as strings. Use <info>-</info> to read from stdin.')]
        string $value,
        #[Option('Format to parse the value: <info>string</info> or <info>yaml</info>. Since JSON is a subset of YAML, the value may be in JSON format.', suggestedValues: self::INPUT_FORMATS)]
        string $inputFormat = 'string',
    ): int {
        if (!in_array($inputFormat, self::INPUT_FORMATS, true)) {
            throw new \InvalidArgumentException((string) $this->t('Unknown input format: @format. Allowed values: @allowed', [
                '@format' => $inputFormat,
                '@allowed' => implode(', ', self::INPUT_FORMATS),
            ]));
        }

        // Special flag indicating that the value has been passed via stdin.
        if ($value === '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // Under CommandTester this reads what setInputs() provided.
            $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
            $value = stream_get_contents($stream ?? STDIN);
        }

        if ($inputFormat === 'yaml') {
            $data = Yaml::parse($value);
        } else {
            // Turn the words null, true and false, and the empty array, into
            // their typed values. Otherwise "false" would be saved as a
            // non-empty string, which the config schema casts to TRUE.
            $data = match (strtolower($value)) {
                'null' => null,
                'true' => true,
                'false' => false,
                '[]' => [],
                default => $value,
            };
        }

        $io = new SymfonyStyle($input, $output);
        $config = $this->configFactory->getEditable($configName);

        if ($key === '?') {
            if (!is_array($data) || $data === []) {
                throw new \InvalidArgumentException((string) $this->t('The ? key needs a mapping of top-level keys to values.'));
            }
            if ($io->confirm((string) $this->t('Do you want to update or set multiple keys on @name config.', ['@name' => $configName]))) {
                foreach ($data as $data_key => $val) {
                    $config->set((string) $data_key, $val);
                }
                $config->save();
            }
            return Command::SUCCESS;
        }

        $question = match (true) {
            $config->isNew() => $this->t('@name config does not exist. Do you want to create a new config object?', ['@name' => $configName]),
            $config->get($key) === null => $this->t('@key key does not exist in @name config. Do you want to create a new config key?', ['@key' => $key, '@name' => $configName]),
            default => $this->t('Do you want to update @key key in @name config?', ['@key' => $key, '@name' => $configName]),
        };
        if ($io->confirm((string) $question)) {
            $config->set($key, $data)->save();
        }

        return Command::SUCCESS;
    }
}
