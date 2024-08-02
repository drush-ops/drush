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

final class DeployHookCommands extends DrushCommands
{
    use AutowireTrait;

    const HOOK_STATUS = 'deploy:hook-status';
    const HOOK = 'deploy:hook';
    const BATCH_PROCESS = 'deploy:batch-process';
    const MARK_COMPLETE = 'deploy:mark-complete';

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

        $process = $this->processManager()->drush($this->siteAliasManager->getSelf(), self::HOOK_STATUS);
        $process->mustRun();
        $this->output()->writeln($process->getOutput());

        if (!$this->io()->confirm(dt('Do you wish to run the specified pending deploy hooks?'))) {
            throw new UserAbortException();
        }

        $success = true;
        if (!$this->getConfig()->simulate()) {
            $operations = [];
            foreach ($pending as $function) {
                $operations[] = ['\Drush\Commands\core\DeployHookCommands::updateDoOneDeployHook', [$function]];
            }

            $batch = [
                'operations' => $operations,
                'title' => 'Updating',
                'init_message' => 'Starting deploy hooks',
                'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
                'finished' => [$this, 'updateFinished'],
            ];
            batch_set($batch);
            $result = drush_backend_batch_process(self::BATCH_PROCESS);

            $success = false;
            if (!is_array($result)) {
                $this->logger()->error(dt('Batch process did not return a result array. Returned: !type', ['!type' => gettype($result)]));
            } elseif (!empty($result[0]['#abort'])) {
                // Whenever an error occurs the batch process does not continue, so
                // this array should only contain a single item, but we still output
                // all available data for completeness.
                $this->logger()->error(dt('Update aborted by: !process', [
                    '!process' => implode(', ', $result[0]['#abort']),
                ]));
            } else {
                $success = true;
            }
        }

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
}
