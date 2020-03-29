<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\Log\ConsoleLogLevel;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\Core\Utility\Error;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Psr\Log\LogLevel;

class DeployHookCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * @var \Drupal\Core\Update\UpdateRegistry
     */
    protected $registry;

    /**
     * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     */
    protected $keyValue;

    /**
     * DeployHookCommands constructor.
     *
     * @param string $root
     *   The app root.
     * @param string $site_path
     *   The site path.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
     *   The key value store factory.
     */
    public function __construct($root, $site_path, ModuleHandlerInterface $moduleHandler, KeyValueFactoryInterface $keyValueFactory)
    {
        parent::__construct();
        $this->keyValue = $keyValueFactory->get('deploy_hook');
        $this->registry = new class(
            $root,
            $site_path,
            array_keys($moduleHandler->getModuleList()),
            $this->keyValue
        ) extends UpdateRegistry {
            public function setUpdateType($type)
            {
                $this->updateType = $type;
            }
        };
        $this->registry->setUpdateType('deploy');
    }

    /**
     * Prints information about pending deploy update hooks.
     *
     * @usage deploy:hook-info
     *   Prints information about pending deploy hooks.
     *
     * @field-labels
     *   module: Module
     *   hook: Hook
     *   description: Description
     * @default-fields module,hook,description
     *
     * @command deploy:hook-info
     *
     * @filter-default-field hook
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function info()
    {
        $updates = $this->registry->getPendingUpdateInformation();
        $rows = [];
        foreach ($updates as $module => $update) {
            if (!empty($update['pending'])) {
                foreach ($update['pending'] as $hook => $description) {
                    $rows[] = [
                        'module' => $module,
                        'hook' => $hook,
                        'description' => $description,
                    ];
                }
            }
        }

        return new RowsOfFields($rows);
    }

    /**
     * Run pending deploy update hooks.
     *
     * @usage deploy:hook
     *   Run pending deploy hooks.
     *
     * @command deploy:hook
     */
    public function run()
    {
        $pending = $this->registry->getPendingUpdateFunctions();

        if (empty($pending)) {
            $this->logger()->success(dt('No pending deploy hooks.'));
            return self::EXIT_SUCCESS;
        }

        $process = $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'deploy:hook-info');
        $process->mustRun();
        $this->output()->writeln($process->getOutput());

        if (!$this->io()->confirm(dt('Do you wish to run the specified pending deploy hooks?'))) {
            throw new UserAbortException();
        }

        $success = true;
        if (!$this->getConfig()->simulate()) {
            try {
                foreach ($pending as $function) {
                    $func = new \ReflectionFunction($function);
                    $this->logger()->notice('Deploy hook started: ' . $func->getName());

                    // Pretend it is a batch operation to keep the same signature
                    // as the post update hooks.
                    $sandbox = [];
                    do {
                        $return = $function($sandbox);
                        if (!empty($return)) {
                            $this->logger()->notice($return);
                        }
                    } while (isset($sandbox['#finished']) && $sandbox['#finished'] < 1);

                    $this->registry->registerInvokedUpdates([$function]);
                    $this->logger()->debug('Performed: ' . $func->getName());
                }
            } catch (\Throwable $e) {
                $variables = Error::decodeException($e);
                unset($variables['backtrace']);
                $this->logger()->error(dt('%type: @message in %function (line %line of %file).', $variables));
                $success = false;
            }
        }

        $level = $success ? ConsoleLogLevel::SUCCESS : LogLevel::ERROR;
        $this->logger()->log($level, dt('Finished performing deploy hooks.'));
        return $success ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }

    /**
     * Marks a deploy update hook as not having run.
     *
     * During development one often wants to re-run the hook, so this helps
     * re-setting it so that it can be run again.
     *
     * @usage deploy:hook-reset mymodule_deploy_runagain
     *   Unregisters that a deploy hook has run so that it runs again.
     *
     * @param string $hook
     *   The hook name to reset.
     *
     * @command deploy:hook-reset
     */
    public function reset(string $hook)
    {
        // We set the values directly in the key value store that is shared
        // with the registry. This avoids adding methods to the anonymous class.
        $executed_hooks = $this->keyValue->get('existing_updates', []);
        $new_hooks = array_diff($executed_hooks, [$hook]);
        $this->keyValue->set('existing_updates', $new_hooks);

        if ($executed_hooks == $new_hooks) {
            $this->logger()->warning(dt('Deploy hook %hook has not run yet.', ['%hook' => $hook]));
            return self::EXIT_SUCCESS;
        }

        $this->logger()->success(dt('Deploy hook %hook reset.', ['%hook' => $hook]));
        return self::EXIT_SUCCESS;
    }
}
