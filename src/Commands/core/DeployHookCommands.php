<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\Core\Utility\Error;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Log\SuccessInterface;
use Psr\Log\LogLevel;

use function OpenTelemetry\Instrumentation\hook;

final class DeployHookCommands extends DrushCommands
{
    use AutowireTrait;

    const HOOK_STATUS = 'deploy:hook-status';
    const HOOK = 'deploy:hook';
    const BATCH_PROCESS = 'deploy:batch-process';
    const MARK_COMPLETE = 'deploy:mark-complete';
    const HOOK_LIST = 'deploy:hook-list';
    const HOOK_UNSET =   'deploy:hook-unset';
    const UPDATE_TYPE = '_deploy_';
    const HOOK_REDEPLOY = 'deploy:redeploy';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Get the deploy hook update registry.
     */
    public static function getRegistry(): UpdateRegistry
    {
        return new class (
            \Drupal::getContainer()->getParameter('app.root'),
            \Drupal::getContainer()->getParameter('site.path'),
            \Drupal::service('module_handler')->getModuleList(),
            \Drupal::service('keyvalue'),
            \Drupal::service('theme_handler'),
        ) extends UpdateRegistry {
            public function __construct(
                $root,
                $site_path,
                $module_list,
                KeyValueFactoryInterface $key_value_factory,
                ThemeHandlerInterface $theme_handler,
            ) {
                // Do not call the parent constructor, we set the properties directly.
                // We need a different key value store and set the update type.
                $this->root = $root;
                $this->sitePath = $site_path;
                $this->enabledExtensions = array_merge(array_keys($module_list), array_keys($theme_handler->listInfo()));
                $this->keyValue = $key_value_factory->get('deploy_hook');
                $this->updateType = 'deploy';
            }
        };
    }

    /**
     * Prints information about pending deploy update hooks.
     */
    #[CLI\Command(name: self::HOOK_STATUS)]
    #[CLI\Usage(name: 'drush deploy:hook-status', description: 'Prints information about pending deploy hooks.')]
    #[CLI\FieldLabels(labels: ['module' => 'Module', 'hook' => 'Hook', 'description' => 'Description'])]
    #[CLI\DefaultTableFields(fields: ['module', 'hook', 'description'])]
    #[CLI\FilterDefaultField(field: 'hook')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function status(): RowsOfFields
    {
        $updates = self::getRegistry()->getPendingUpdateInformation();
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
     */
    #[CLI\Command(name: self::HOOK)]
    #[CLI\Usage(name: 'drush ' . self::HOOK, description: 'Run pending deploy hooks.')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Version(version: '10.3')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function run(): int
    {
        $pending = self::getRegistry()->getPendingUpdateFunctions();

        if (empty($pending)) {
            $this->logger()->success(dt('No pending deploy hooks.'));
            return self::EXIT_SUCCESS;
        }

        $process = $this->processManager()->drush($this->siteAliasManager->getSelf(), self::HOOK_STATUS, [], Drush::redispatchOptions() + ['strict' => 0]);
        $process->mustRun();
        $this->output()->writeln($process->getOutput());

        if (!$this->io()->confirm(dt('Do you wish to run the specified pending deploy hooks?'))) {
            throw new UserAbortException();
        }

        $success = $this->batchOperation($pending);
        $level = $success ? SuccessInterface::SUCCESS : LogLevel::ERROR;
        $this->logger()->log($level, dt('Finished performing deploy hooks.'));
        return $success ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }

    /**
     * Process operations in the specified batch set.
     */
    #[CLI\Command(name: self::BATCH_PROCESS)]
    #[CLI\Argument(name: 'batch_id', description: 'The batch id that will be processed.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    #[CLI\Help(hidden: true)]
    public function process(string $batch_id, $options = ['format' => 'json']): UnstructuredListData
    {
        $result = drush_batch_command($batch_id);
        return new UnstructuredListData($result);
    }

    /**
     * Batch command that executes a single deploy hook.
     */
    public static function updateDoOneDeployHook(string $function, array $context): void
    {
        $ret = [];

        // If this update was aborted in a previous step, or has a dependency that was
        // aborted in a previous step, go no further.
        if (!empty($context['results']['#abort'])) {
            return;
        }

        // Module names can include '_deploy', so deploy functions like
        // module_deploy_deploy_name() are ambiguous. Check every occurrence.
        $components = explode('_', $function);
        foreach (array_keys($components, 'deploy', true) as $position) {
            $module = implode('_', array_slice($components, 0, $position));
            $name = implode('_', array_slice($components, $position + 1));
            $filename = $module . '.deploy';
            \Drupal::moduleHandler()->loadInclude($module, 'php', $filename);
            if (function_exists($function)) {
                break;
            }
        }
        assert(isset($module) && isset($name) && isset($filename));

        if (function_exists($function)) {
            if (empty($context['results'][$module][$name]['type'])) {
                Drush::logger()->notice("Deploy hook started: $function");
            }
            try {
                $ret['results']['query'] = $function($context['sandbox']);
                $ret['results']['success'] = true;
                $ret['type'] = 'deploy';

                if (!isset($context['sandbox']['#finished']) || (isset($context['sandbox']['#finished']) && $context['sandbox']['#finished'] >= 1)) {
                    self::getRegistry()->registerInvokedUpdates([$function]);
                }
            } catch (\Exception $e) {
                // @TODO We may want to do different error handling for different exception
                // types, but for now we'll just log the exception and return the message
                // for printing.
                // @see https://www.drupal.org/node/2564311
                Drush::logger()->error($e->getMessage());

                $variables = Error::decodeException($e);
                $variables = array_filter($variables, function ($key) {
                    return $key[0] === '@' || $key[0] === '%';
                }, ARRAY_FILTER_USE_KEY);
                // On windows there is a problem with json encoding a string with backslashes.
                $variables['%file'] = strtr($variables['%file'], [DIRECTORY_SEPARATOR => '/']);
                $ret['#abort'] = [
                    'success' => false,
                    'query' => strip_tags((string) t('%type: @message in %function (line %line of %file).', $variables)),
                ];
            }
        } else {
            $ret['#abort'] = ['success' => false];
            Drush::logger()->warning(dt('Deploy hook function @function not found in file @filename', [
                '@function' => $function,
                '@filename' => "$filename.php",
            ]));
        }

        if (isset($context['sandbox']['#finished'])) {
            $context['finished'] = $context['sandbox']['#finished'];
            unset($context['sandbox']['#finished']);
        }
        if (!isset($context['results'][$module][$name])) {
            $context['results'][$module][$name] = [];
        }
        $context['results'][$module][$name] = array_merge($context['results'][$module][$name], $ret);

        // Log the message that was returned.
        if (!empty($ret['results']['query'])) {
            Drush::logger()->notice(strip_tags((string) $ret['results']['query']));
        }

        if (!empty($ret['#abort'])) {
            // Record this function in the list of updates that were aborted.
            $context['results']['#abort'][] = $function;
            Drush::logger()->error("Deploy hook failed: $function");
        } elseif ($context['finished'] == 1 && empty($ret['#abort'])) {
            $context['message'] = "Performed: $function";
        }
    }

    /**
     * Batch finished callback.
     *
     * @param boolean $success Whether the batch ended without a fatal error.
     */
    public function updateFinished(bool $success, array $results, array $operations): void
    {
        // In theory there is nothing to do here.
    }

    /**
     * Mark all deploy hooks as having run.
     */
    #[CLI\Command(name: self::MARK_COMPLETE)]
    #[CLI\Usage(name: 'drush deploy:mark-complete', description: 'Skip all pending deploy hooks and mark them as complete.')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Version(version: '10.6.1')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function markComplete(): int
    {
        $pending = self::getRegistry()->getPendingUpdateFunctions();
        self::getRegistry()->registerInvokedUpdates($pending);

        $this->logger()->success(dt('Marked %count pending deploy hooks as complete.', ['%count' => count($pending)]));
        return self::EXIT_SUCCESS;
    }

  /**
   * Prints information about deployed hooks.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
    #[CLI\Command(name: self::HOOK_LIST)]
    #[CLI\Usage(name: 'drush deploy:hook-list', description: 'Prints information about deployed hooks.')]
    #[CLI\FieldLabels(labels: [
    'module'      => 'Module',
    'hook'        => 'Hook',
    ])]
    #[CLI\DefaultTableFields(fields: ['module', 'hook'])]
    #[CLI\FilterDefaultField(field: 'hook')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function list(): RowsOfFields
    {
        $update_functions = $this->getDeployedHooks();

        $updates = [];
        foreach ($update_functions as $function) {
          // Validate function name format.
            if (!str_contains($function, self::UPDATE_TYPE)) {
                $this->logger()->warning("Skipping invalid hook function: {function}", ['function' => $function]);
                continue;
            }

          // Split function name into extension and update.
            [$extension, $update] = explode(self::UPDATE_TYPE, $function);
            if (empty($extension) || empty($update)) {
                $this->logger()->warning("Invalid hook function format: {function}", ['function' => $function]);
                continue;
            }

          // Store the update data.
            $updates[$extension]['deployed'][$update] = true;
            if (!isset($updates[$extension]['start'])) {
                $updates[$extension]['start'] = $update;
            }
        }
        $rows = [];
        foreach ($updates as $module => $update_data) {
            foreach ($update_data['deployed'] as $hook => $value) {
                $rows[] = [
                'module' => $module,
                'hook' => $hook,
                ];
            }
        }
        return new RowsOfFields($rows);
    }

  /**
   * Unsets a hook from the deployed hooks list.
   *
   * @param string $hook_name The name of the hook to remove (e.g., hook_deploy_NAME)
   *
   * @return int Exit code
   */
    #[CLI\Command(name: self::HOOK_UNSET)]
    #[CLI\Argument(name: 'hook_name', description: 'The name of the hook to remove (e.g., hook_deploy_NAME)')]
    #[CLI\Usage(
        name: 'drush deploy:hook-unset hook_deploy_NAME',
        description: 'Removes the specified hook from the deployed hooks list'
    )]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function unset(string $hook_name): int
    {
        $deployed_hooks = $this->getDeployedHooks();
        // Check if the hook exists.
        if (!in_array($hook_name, $deployed_hooks, true)) {
            $this->logger()->warning("Hook {hook} not found in deployed hooks.", [
              'hook' => $hook_name
            ]);
            return self::EXIT_SUCCESS;
        }
        // Remove the hook from the list.
        $update_functions = array_filter($deployed_hooks, function ($function) use ($hook_name) {
            return $function !== $hook_name;
        });

        // Update the deployed hook list.
        \Drupal::service('keyvalue')->get('deploy_hook')->set('existing_updates', $update_functions);
        $this->logger()->success(dt('Hook !hook_name removed from deployed hooks list.', [
        '!hook_name' => $hook_name
        ]));
        return self::EXIT_SUCCESS;
    }

  /**
   * Redeploys a hook.
   *
   * @param string $hook_name
   * The name of the hook to redeploy (e.g., hook_deploy_NAME)
   *
   * @return int
   *  Exit code.
   * @throws \Drush\Exceptions\UserAbortException
   */
    #[CLI\Command(name: self::HOOK_REDEPLOY)]
    #[CLI\Argument(name: 'hook_name', description: 'The name of the hook to redeploy (e.g., hook_deploy_NAME)')]
    #[CLI\Usage(
        name: 'drush deploy:redeploy hook_deploy_NAME',
        description: 'Redeploys the specified hook'
    )]
    #[CLI\Version(version: '10.3')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function redeploy(string $hook_name): int
    {
        $deployed_hooks = $this->getDeployedHooks();
        if (!in_array($hook_name, $deployed_hooks)) {
            $this->logger()->success("Hook {$hook_name} not found in deployed hooks.", [
              'hook' => $hook_name
            ]);
            return self::EXIT_SUCCESS;
        }

        if (!$this->io()->confirm(dt('Do you wish to run the specified deployed hooks?'))) {
            throw new UserAbortException();
        }
        // Build and run the batch process.
        $success = $this->batchOperation([$hook_name]);
        $level = $success ? SuccessInterface::SUCCESS : LogLevel::ERROR;
        $this->logger()->log($level, dt('Finished performing re-deploy hooks.'));
        return $success ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }


  /**
   * Get all deployed hooks.
   *
   * @return array
   */
    public function getDeployedHooks(): array
    {
        $store = \Drupal::service('keyvalue')->get('deploy_hook');
        return $store->get('existing_updates', []);
    }

  /**
   * Build the batch process to run the deployment hooks.
   *
   * @param array<int, string> $hooks
   * An array of function names to be executed as deploy hooks.
   *
   * @return bool
   * TRUE if the batch process was started successfully, FALSE otherwise.
   */
    public function batchOperation(array $hooks): bool
    {
        $success = true;
        if (!$this->getConfig()->simulate()) {
            $operations = [];
            foreach ($hooks as $function) {
                $operations[]
                = [
                '\Drush\Commands\core\DeployHookCommands::updateDoOneDeployHook',
                [$function]
                ];
            }

            $batch = [
            'operations'    => $operations,
            'title'         => 'Updating',
            'init_message'  => 'Starting deploy hooks',
            'error_message' => 'An unrecoverable error has occurred. You can find the error message below. 
            It is advised to copy it to the clipboard for reference.',
            'finished'      => [$this, 'updateFinished'],
            ];
            batch_set($batch);
            $result = drush_backend_batch_process(self::BATCH_PROCESS);

            $success = false;
            if (!is_array($result)) {
                $this->logger()->error(
                    dt(
                        'Batch process did not return a result array. Returned: !type',
                        ['!type' => gettype($result)]
                    )
                );
            } elseif (!empty($result[0]['#abort'])) {
              // Whenever an error occurs, the batch process does not continue, so
              // this array should only contain a single item, but we still output
              // all available data for completeness.
                $this->logger()->error(dt('Update aborted by: !process', [
                '!process' => implode(', ', $result[0]['#abort']),
                ]));
            } else {
                $success = true;
            }
        }

        return $success;
    }
}
