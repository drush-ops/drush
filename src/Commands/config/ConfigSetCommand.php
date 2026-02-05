<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageCacheInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

#[AsCommand(
    name: self::NAME,
    description: 'Save a config value directly. Does not perform a config import.',
    aliases: ['cset', 'config-set']
)]
final class ConfigSetCommand extends Command
{
    use AutowireTrait;
    use ConfigNameTrait;

    public const string NAME = 'config:set';

    public function __construct(
        protected readonly ConfigFactoryInterface $configFactory,
        protected readonly StorageCacheInterface $configStorage,
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->addArgument('config_name', InputArgument::REQUIRED, 'The config object name, for example <info>system.site</info>.')
            ->addArgument('key', InputArgument::REQUIRED, 'The config key, for example <info>page.front</info>. Use <info>?</info> if you are updating multiple top-level keys.')
            ->addArgument('value', InputArgument::REQUIRED, 'The value to assign to the config key. Use <info>-</info> to read from Stdin.')
            ->addOption('input-format', null, InputOption::VALUE_REQUIRED, 'Format to parse the object. Recognized values: <info>string</info>, <info>yaml</info>. Since JSON is a subset of YAML, $value may be in JSON format.', 'string')
            // @todo Move the old descriptions of these Usages into setHelp().
            ->addUsage('config:set system.site name MySite')
            ->addUsage('config:set user.role.anonymous permissions \'[]\'')
            ->addUsage('config:set system.site name \'NULL\'')
            ->addUsage("config:set --input-format=yaml system.site page {403: '403', front: home}")
            ->addUsage('config:set --input-format=yaml user.role.authenticated permissions [foo,bar]')
            ->addUsage('config:set --input-format=yaml user.role.authenticated ? "{label: \'Auth user\', weight: 5}')
            ->addUsage('cat tmp.yml | drush config:set --input-format=yaml user.mail ? -');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $data = $input->getArgument('value');

        // Special flag indicating that the value has been passed via STDIN.
        if ($data === '-') {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // If testing this will get input added by `CommandTester::setInputs` method.
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : STDIN;
            $data = stream_get_contents($inputStream);
        }

        // Special handling for null.
        if (strtolower($data) === 'null') {
            $data = null;
        }

        // Special handling for empty array.
        if ($data == '[]') {
            $data = [];
        }

        if ($input->getOption('input-format') === 'yaml') {
            $parser = new Parser();
            $data = $parser->parse($data);
        }

        $config_name = $input->getArgument('config_name');
        $config = $this->configFactory->getEditable($config_name);
        // Check to see if config key already exists.
        $key = $input->getArgument('key');
        $new_key = $config->get($key) === null;

        if ($key == '?' && !empty($data) && $io->confirm(sprintf('Do you want to update or set multiple keys on %s config.', $config_name))) {
            foreach ($data as $data_key => $val) {
                $config->set($data_key, $val);
            }
            $config->save();
        } else {
            $confirmed = false;
            if ($config->isNew() && $io->confirm(sprintf('%s config does not exist. Do you want to create a new config object?', $config_name))) {
                $confirmed = true;
            } elseif ($new_key && $io->confirm(sprintf('%s key does not exist in %s config. Do you want to create a new config key?', $key, $config_name))) {
                $confirmed = true;
            } elseif ($io->confirm(sprintf('Do you want to update %s key in %s config?', $key, $config_name))) {
                $confirmed = true;
            }
            if ($confirmed) {
                $config->set($key, $data)->save();
            }
        }
        return self::SUCCESS;
    }
}
