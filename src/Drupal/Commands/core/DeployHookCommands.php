<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\Log\ConsoleLogLevel;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\Core\Utility\Error;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use DrushBatchContext;
use Drush\Exceptions\UserAbortException;
use Psr\Log\LogLevel;

class DeployHookCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Get the deploy hook update registry.
     *
     * @return UpdateRegistry
     */
    public static function getRegistry()
    {
        $registry = new class(
            \Drupal::service('app.root'),
            \Drupal::service('site.path'),
            array_keys(\Drupal::service('module_handler')->getModuleList()),
            \Drupal::service('keyvalue')->get('deploy_hook')
        ) extends UpdateRegistry {
            public function setUpdateType($type)
            {
                $this->updateType = $type;
            }
        };
        $registry->setUpdateType('deploy');

        return $registry;
    }

    /**
     * Prints information about pending deploy update hooks.
     *
     * @usage deploy:hook-status
     *   Prints information about pending deploy hooks.
     *
     * @field-labels
     *   module: Module
     *   hook: Hook
     *   description: Description
     * @default-fields module,hook,description
     *
     * @command deploy:hook-status
     * @topics docs:deploy
     *
     * @filter-default-field hook
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function status()
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
     *
     * @usage deploy:hook
     *   Run pending deploy hooks.
     *
     * @command deploy:hook
     * @topics docs:deploy
     */
    public function run()
    {
        $pending = self::getRegistry()->getPendingUpdateFunctions();

        if (empty($pending)) {
            $this->logger()->success(dt('No pending deploy hooks.'));
            return self::EXIT_SUCCESS;
        }

        $process = $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'deploy:hook-status');
        $process->mustRun();
        $this->output()->writeln($process->getOutput());

        if (!$this->io()->confirm(dt('Do you wish to run the specified pending deploy hooks?'))) {
            throw new UserAbortException();
        }

        $success = true;
        if (!$this->getConfig()->simulate()) {
            $operations = [];
            foreach ($pending as $function) {
                $operations[] = ['\Drush\Drupal\Commands\core\DeployHookCommands::updateDoOneDeployHook', [$function]];
            }

            $batch = [
                'operations' => $operations,
                'title' => 'Updating',
                'init_message' => 'Starting deploy hooks',
                'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
                'finished' => [$this, 'updateFinished'],
            ];
            batch_set($batch);
            $result = drush_backend_batch_process('deploy:batch-process');

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

        $level = $success ? ConsoleLogLevel::SUCCESS : LogLevel::ERROR;
        $this->logger()->log($level, dt('Finished performing deploy hooks.'));
        return $success ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }

    /**
     * Process operations in the specified batch set.
     *
     * @command deploy:batch-process
     * @param string $batch_id The batch id that will be processed.
     * @bootstrap full
     * @hidden
     *
     * @return \Consolidation\OutputFormatters\StructuredData\UnstructuredListData
     */
    public function process($batch_id, $options = ['format' => 'json'])
    {
        $result = drush_batch_command($batch_id);
        return new UnstructuredListData($result);
    }

    /**
     * Batch command that executes a single deploy hook.
     *
     * @param string $function
     *   The deploy-hook function to execute.
     * @param DrushBatchContext $context
     *   The batch context object.
     */
    public static function updateDoOneDeployHook($function, DrushBatchContext $context)
    {
        $ret = [];

        // If this update was aborted in a previous step, or has a dependency that was
        // aborted in a previous step, go no further.
        if (!empty($context['results']['#abort'])) {
            return;
        }

        list($module, $name) = explode('_deploy_', $function, 2);
        $filename = $module . '.deploy';
        \Drupal::moduleHandler()->loadInclude($module, 'php', $filename);
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
                unset($variables['backtrace']);
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
            // Setting this value will output an error message.
            // @see \DrushBatchContext::offsetSet()
            $context['error_message'] = "Deploy hook failed: $function";
        } elseif ($context['finished'] == 1 && empty($ret['#abort'])) {
            // Setting this value will output a success message.
            // @see \DrushBatchContext::offsetSet()
            $context['message'] = "Performed: $function";
        }
    }

    /**
     * Batch finished callback.
     *
     * @param boolean $success Whether the batch ended without a fatal error.
     * @param array $results
     * @param array $operations
     */
    public function updateFinished($success, $results, $operations)
    {
        // In theory there is nothing to do here.
    }

    /**
     * Mark all deploy hooks as having run.
     *
     * @usage deploy:mark-complete
     *   Skip all pending deploy hooks and mark them as complete.
     *
     * @command deploy:mark-complete
     * @topics docs:deploy
     */
    public function markComplete()
    {
        $pending = self::getRegistry()->getPendingUpdateFunctions();
        self::getRegistry()->registerInvokedUpdates($pending);

        $this->logger()->success(dt('Marked %count pending deploy hooks as complete.', ['%count' => count($pending)]));
        return self::EXIT_SUCCESS;
    }
}
