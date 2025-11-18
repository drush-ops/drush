<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exec\ExecTrait;
use Drush\Utils\StringUtils;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ConfigCommands extends DrushCommands implements StdinAwareInterface
{
    use AutowireTrait;
    use StdinAwareTrait;
    use ExecTrait;

    const string INTERACT_CONFIG_NAME = 'interact-config-name';
    const string VALIDATE_CONFIG_NAME = 'validate-config-name';
    #[Deprecated(reason: 'Use ConfigGetCommand::NAME')]
    const string GET = 'config:get';
    #[Deprecated(replacement: ConfigSetCommand::NAME)]
    const string SET = 'config:set';
    #[Deprecated(reason: 'Use ConfigEditCommand::NAME')]
    const string EDIT = 'config:edit';
    #[Deprecated(reason: 'Use ConfigDeleteCommand::NAME')]
    const string DELETE = 'config:delete';
    #[Deprecated(reason: 'Use ConfigStatusCommand::NAME')]
    const string STATUS = 'config:status';

    public function getConfigFactory(): ConfigFactoryInterface
    {
        return $this->configFactory;
    }

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        #[Autowire(service: 'config.storage')]
        protected StorageInterface $configStorage,
        protected SiteAliasManagerInterface $siteAliasManager,
        protected StorageManagerInterface $configStorageExport,
        protected ImportStorageTransformer $importStorageTransformer,
    ) {
        parent::__construct();
    }

    #[Deprecated('Use an interact() method instead')]
    #[CLI\Hook(type: HookManager::INTERACT, selector: self::INTERACT_CONFIG_NAME)]
    public function interactConfigName($input, $output): void
    {
        if (empty($input->getArgument('config_name'))) {
            $config_names = $this->getConfigFactory()->listAll();
            $choice = $this->io()->suggest('Choose a configuration', array_combine($config_names, $config_names), scroll: 200, required: true);
            $input->setArgument('config_name', $choice);
        }
    }

    /**
     * Validate that a config name is valid.
     */
    #[Deprecated('Use CLI/ValidateConfigName Attribute instead')]
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, selector: self::VALIDATE_CONFIG_NAME)]
    public function validateConfigName(CommandData $commandData): ?CommandError
    {
        $arg_name = $commandData->annotationData()->get(self::VALIDATE_CONFIG_NAME);
        $config_name = $commandData->input()->getArgument($arg_name);
        $names = StringUtils::csvToArray($config_name);
        foreach ($names as $name) {
            $config = \Drupal::config($name);
            if ($config->isNew()) {
                $msg = dt('Config !name does not exist', ['!name' => $name]);
                return new CommandError($msg);
            }
        }
        return null;
    }
}
