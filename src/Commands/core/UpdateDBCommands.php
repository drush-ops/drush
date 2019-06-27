<?php
namespace Drush\Commands\core;

use Consolidation\Log\ConsoleLogLevel;
use DrushBatchContext;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Utility\Error;
use Drupal\Core\Entity\EntityStorageException;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Psr\Log\LogLevel;

class UpdateDBCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    protected $cache_clear;

    protected $maintenanceModeOriginalState;

    /**
     * Apply any database updates required (as with running update.php).
     *
     * @command updatedb
     * @option cache-clear Clear caches upon completion.
     * @option entity-updates Run automatic entity schema updates at the end of any update hooks. Not supported in Drupal >= 8.7.0.
     * @option post-updates Run post updates after hook_update_n and entity updates.
     * @bootstrap full
     * @kernel update
     * @aliases updb
     */
    public function updatedb($options = ['cache-clear' => true, 'entity-updates' => false, 'post-updates' => true])
    {
        $this->cache_clear = $options['cache-clear'];
        require_once DRUPAL_ROOT . '/core/includes/install.inc';
        require_once DRUPAL_ROOT . '/core/includes/update.inc';
        drupal_load_updates();

        if ($options['entity-updates'] && version_compare(drush_drupal_version(), '8.7.0', '>=')) {
            $this->logger()->warning(dt('Drupal removed its automatic entity-updates API in 8.7. See https://www.drupal.org/node/3034742.'));
            $options['entity-updates'] = false;
        }

        // Disables extensions that have a lower Drupal core major version, or too high of a PHP requirement.
        // Those are rare, and this function does a full rebuild. So commenting it out for now.
        // update_fix_compatibility();

        // Check requirements before updating.
        if (!$this->updateCheckRequirements()) {
            if (!$this->io()->confirm(dt('Requirements check reports errors. Do you wish to continue?'))) {
                throw new UserAbortException();
            }
        }

        $status_options = [
            // @see https://github.com/drush-ops/drush/pull/3855.
            'no-entity-updates' => !$options['entity-updates'],
            'no-post-updates' => !$options['post-updates'],
        ];
        $process = $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'updatedb:status', [], $status_options);
        $process->mustRun();
        if ($output = $process->getOutput()) {
            // We have pending updates - let's run em.
            $this->output()->writeln($output);
            if (!$this->io()->confirm(dt('Do you wish to run the specified pending updates?'))) {
                throw new UserAbortException();
            }
            if ($this->getConfig()->simulate()) {
                $success = true;
            } else {
                $success = $this->updateBatch($options);
                // Caches were just cleared in updateFinished callback.
            }

            $level = $success ? ConsoleLogLevel::SUCCESS : LogLevel::ERROR;
            $this->logger()->log($level, dt('Finished performing updates.'));
        } else {
            $this->logger()->success(dt('No pending updates.'));
            $success = true;
        }

        return $success ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }

    /**
     * Apply pending entity schema updates.
     *
     * @command entity:updates
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @bootstrap full
     * @kernel update
     * @aliases entup,entity-updates
     * @usage drush updatedb:status --entity-updates | grep entity-update
     *   Use updatedb:status to detect pending updates.
     *
     */
    public function entityUpdates($options = ['cache-clear' => true])
    {
        if ($this->getConfig()->simulate()) {
            throw new \Exception(dt('entity-updates command does not support --simulate option.'));
        }

        // @todo - Do same check for updatedb as well.
        if (version_compare(drush_drupal_version(), '8.7.0', '>=')) {
            throw new \Exception(dt('Drupal removed its automatic entity-updates API in 8.7. See https://www.drupal.org/node/3034742.'));
        }

        if ($this->entityUpdatesMain() === false) {
            throw new \Exception('Entity updates not run.');
        }

        if ($options['cache-clear']) {
            drush_drupal_cache_clear_all();
        }

        $this->logger()->success(dt('Finished performing updates.'));
    }

    /**
     * List any pending database updates.
     *
     * @command updatedb:status
     * @option entity-updates Show entity schema updates.
     * @option post-updates Show post updates.
     * @bootstrap full
     * @kernel update
     * @aliases updbst,updatedb-status
     * @field-labels
     *   module: Module
     *   update_id: Update ID
     *   description: Description
     *   type: Type
     * @default-fields module,update_id,type,description
     * @filter-default-field type
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function updatedbStatus($options = ['format'=> 'table', 'entity-updates' => true, 'post-updates' => true])
    {
        require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        drupal_load_updates();
        list($pending, $start) = $this->getUpdatedbStatus($options);
        if (empty($pending)) {
            $this->logger()->success(dt("No database updates required."));
        } else {
            return new RowsOfFields($pending);
        }
    }

    /**
     * Process operations in the specified batch set.
     *
     * @command updatedb:batch-process
     * @param string $batch_id The batch id that will be processed.
     * @bootstrap full
     * @kernel update
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
     * Perform one update and store the results which will later be displayed on
     * the finished page.
     *
     * An update function can force the current and all later updates for this
     * module to abort by returning a $ret array with an element like:
     * $ret['#abort'] = array('success' => FALSE, 'query' => 'What went wrong');
     * The schema version will not be updated in this case, and all the
     * aborted updates will continue to appear on update.php as updates that
     * have not yet been run.
     *
     * This method is static since since it is called by _drush_batch_worker().
     *
     * @param string $module
     *   The module whose update will be run.
     * @param int $number
     *   The update number to run.
     * @param array $dependency_map
     *   The update dependency map.
     * @param DrushBatchContext $context
     *   The batch context object.
     */
    public static function updateDoOne($module, $number, array $dependency_map, DrushBatchContext $context)
    {
        $function = $module . '_update_' . $number;

        // Disable config entity overrides.
        if (!defined('MAINTENANCE_MODE')) {
            define('MAINTENANCE_MODE', 'update');
        }

        // If this update was aborted in a previous step, or has a dependency that
        // was aborted in a previous step, go no further.
        if (!empty($context['results']['#abort']) && array_intersect($context['results']['#abort'], array_merge($dependency_map, [$function]))) {
            return;
        }

        $context['log'] = false;

        \Drupal::moduleHandler()->loadInclude($module, 'install');

        $ret = [];
        if (function_exists($function)) {
            try {
                if ($context['log']) {
                    Database::startLog($function);
                }

                if (empty($context['results'][$module][$number]['type'])) {
                    Drush::logger()->notice("Update started: $function");
                }

                $ret['results']['query'] = $function($context['sandbox']);
                $ret['results']['success'] = true;
                $ret['type'] = 'update';
            } catch (\Throwable $e) {
                // PHP 7 introduces Throwable, which covers both Error and Exception throwables.
                $ret['#abort'] = ['success' => false, 'query' => $e->getMessage()];
                Drush::logger()->error($e->getMessage());
            } catch (\Exception $e) {
                // In order to be compatible with PHP 5 we also catch regular Exceptions.
                $ret['#abort'] = ['success' => false, 'query' => $e->getMessage()];
                Drush::logger()->error($e->getMessage());
            }

            if ($context['log']) {
                $ret['queries'] = Database::getLog($function);
            }
        } else {
            $ret['#abort'] = ['success' => false];
            Drush::logger()->warning(dt('Update function @function not found', ['@function' => $function]));
        }

        if (isset($context['sandbox']['#finished'])) {
            $context['finished'] = $context['sandbox']['#finished'];
            unset($context['sandbox']['#finished']);
        }

        if (!isset($context['results'][$module])) {
            $context['results'][$module] = [];
        }
        if (!isset($context['results'][$module][$number])) {
            $context['results'][$module][$number] = [];
        }
        $context['results'][$module][$number] = array_merge($context['results'][$module][$number], $ret);

        // Log the message that was returned.
        if (!empty($ret['results']['query'])) {
            Drush::logger()->notice(strip_tags((string) $ret['results']['query']));
        }

        if (!empty($ret['#abort'])) {
            // Record this function in the list of updates that were aborted.
            $context['results']['#abort'][] = $function;
            // Setting this value will output an error message.
            // @see \DrushBatchContext::offsetSet()
            $context['error_message'] = "Update failed: $function";
        }

        // Record the schema update if it was completed successfully.
        if ($context['finished'] == 1 && empty($ret['#abort'])) {
            drupal_set_installed_schema_version($module, $number);
            // Setting this value will output a success message.
            // @see \DrushBatchContext::offsetSet()
            $context['message'] = "Update completed: $function";
        }
    }

    /**
     * Batch command that executes a single post-update.
     *
     * @param string $function
     *   The post-update function to execute.
     * @param DrushBatchContext $context
     *   The batch context object.
     */
    public static function updateDoOnePostUpdate($function, DrushBatchContext $context)
    {
        $ret = [];

        // Disable config entity overrides.
        if (!defined('MAINTENANCE_MODE')) {
            define('MAINTENANCE_MODE', 'update');
        }

        // If this update was aborted in a previous step, or has a dependency that was
        // aborted in a previous step, go no further.
        if (!empty($context['results']['#abort'])) {
            return;
        }

        list($module, $name) = explode('_post_update_', $function, 2);
        module_load_include('php', $module, $module . '.post_update');
        if (function_exists($function)) {
            if (empty($context['results'][$module][$name]['type'])) {
                Drush::logger()->notice("Update started: $function");
            }
            try {
                $ret['results']['query'] = $function($context['sandbox']);
                $ret['results']['success'] = true;
                $ret['type'] = 'post_update';

                if (!isset($context['sandbox']['#finished']) || (isset($context['sandbox']['#finished']) && $context['sandbox']['#finished'] >= 1)) {
                    \Drupal::service('update.post_update_registry')->registerInvokedUpdates([$function]);
                }
            } catch (\Exception $e) {
                // @TODO We may want to do different error handling for different exception
                // types, but for now we'll just log the exception and return the message
                // for printing.
                // @see https://www.drupal.org/node/2564311
                Drush::logger()->error($e->getMessage());

                $variables = Error::decodeException($e);
                unset($variables['backtrace']);
                $ret['#abort'] = [
                    'success' => false,
                    'query' => t('%type: @message in %function (line %line of %file).', $variables),
                ];
            }
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
            $context['error_message'] = "Update failed: $function";
        } elseif ($context['finished'] == 1 && empty($ret['#abort'])) {
            // Setting this value will output a success message.
            // @see \DrushBatchContext::offsetSet()
            $context['message'] = "Update completed: $function";
        }
    }

    /**
     * Batch finished callback.
     *
     * @param boolean $success Whether the batch ended without a fatal error.
     * @param array $results
     * @param array $operations
     */
    public static function updateFinished($success, $results, $operations)
    {
        // No code needed but the batch result bookkeeping fails without a finished callback.
        $noop = 1;
    }


    /**
     * Start the database update batch process.
     * @param $options
     * @return bool
     */
    public function updateBatch($options)
    {
        $start = $this->getUpdateList();
        // Resolve any update dependencies to determine the actual updates that will
        // be run and the order they will be run in.
        $updates = update_resolve_dependencies($start);

        // Store the dependencies for each update function in an array which the
        // batch API can pass in to the batch operation each time it is called. (We
        // do not store the entire update dependency array here because it is
        // potentially very large.)
        $dependency_map = [];
        foreach ($updates as $function => $update) {
            $dependency_map[$function] = !empty($update['reverse_paths']) ? array_keys($update['reverse_paths']) : [];
        }

        $operations = [];

        foreach ($updates as $update) {
            if ($update['allowed']) {
                // Set the installed version of each module so updates will start at the
                // correct place. (The updates are already sorted, so we can simply base
                // this on the first one we come across in the above foreach loop.)
                if (isset($start[$update['module']])) {
                    drupal_set_installed_schema_version($update['module'], $update['number'] - 1);
                    unset($start[$update['module']]);
                }
                // Add this update function to the batch.
                $function = $update['module'] . '_update_' . $update['number'];
                $operations[] = ['\Drush\Commands\core\UpdateDBCommands::updateDoOne', [$update['module'], $update['number'], $dependency_map[$function]]];
            }
        }

        // Perform entity definition updates, which will update storage
        // schema if needed. If module update functions need to work with specific
        // entity schema they should call the entity update service for the specific
        // update themselves.
        // @see \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface::applyEntityUpdate()
        // @see \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface::applyFieldUpdate()
        if ($options['entity-updates'] && \Drupal::entityDefinitionUpdateManager()->needsUpdates()) {
            $operations[] = [[$this, 'updateEntityDefinitions'], []];
        }

        // Lastly, apply post update hooks if specified.
        if ($options['post-updates']) {
            $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateFunctions();
            if ($post_updates) {
                if ($operations) {
                    // Only needed if we performed updates earlier.
                    $operations[] = ['\Drush\Commands\core\UpdateDBCommands::cacheRebuild', []];
                }
                foreach ($post_updates as $function) {
                    $operations[] = ['\Drush\Commands\core\UpdateDBCommands::updateDoOnePostUpdate', [$function]];
                }
            }
        }

        $original_maint_mode = \Drupal::service('state')->get('system.maintenance_mode');
        if (!$original_maint_mode) {
            \Drupal::service('state')->set('system.maintenance_mode', true);
            $operations[] = ['\Drush\Commands\core\UpdateDBCommands::restoreMaintMode', [false]];
        }

        $batch['operations'] = $operations;
        $batch += [
            'title' => 'Updating',
            'init_message' => 'Starting updates',
            'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
            'finished' => '\Drush\Commands\core\UpdateDBCommands::updateFinished',
            'file' => 'core/includes/update.inc',
        ];
        batch_set($batch);
        $result = drush_backend_batch_process('updatedb:batch-process');

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

        return $success;
    }

    public static function restoreMaintMode($status)
    {
        \Drupal::service('state')->set('system.maintenance_mode', $status);
    }

    /**
     * Apply entity schema updates.
     */
    public function updateEntityDefinitions(&$context)
    {
        try {
            \Drupal::entityDefinitionUpdateManager()->applyupdates();
        } catch (EntityStorageException $e) {
            watchdog_exception('update', $e);
            $variables = Error::decodeException($e);
            unset($variables['backtrace']);
            // The exception message is run through
            // \Drupal\Component\Utility\SafeMarkup::checkPlain() by
            // \Drupal\Core\Utility\Error::decodeException().
            $ret['#abort'] = ['success' => false, 'query' => t('%type: !message in %function (line %line of %file).', $variables)];
            $context['results']['core']['update_entity_definitions'] = $ret;
            $context['results']['#abort'][] = 'update_entity_definitions';
        }
    }

    // Copy of protected \Drupal\system\Controller\DbUpdateController::getModuleUpdates.
    public function getUpdateList()
    {
        $return = [];
        $updates = update_get_update_list();
        foreach ($updates as $module => $update) {
            $return[$module] = $update['start'];
        }

        return $return;
    }

    /**
     * Clears caches and rebuilds the container.
     *
     * This is called in between regular updates and post updates. Do not use
     * drush_drupal_cache_clear_all() as the cache clearing and container rebuild
     * must happen in the same process that the updates are run in.
     *
     * Drupal core's update.php uses drupal_flush_all_caches() directly without
     * explicitly rebuilding the container as the container is rebuilt on the next
     * HTTP request of the batch.
     *
     * @see drush_drupal_cache_clear_all()
     * @see \Drupal\system\Controller\DbUpdateController::triggerBatch()
     */
    public static function cacheRebuild()
    {
        drupal_flush_all_caches();
        \Drupal::service('kernel')->rebuildContainer();
        // Load the module data which has been removed when the container was
        // rebuilt.
        $module_handler = \Drupal::moduleHandler();
        $module_handler->loadAll();
        $module_handler->invokeAll('rebuild');
    }

    /**
     * Return a 2 item array with
     *  - an array where each item is a 4 item associative array describing a pending update.
     *  - an array listing the first update to run, keyed by module.
     */
    public function getUpdatedbStatus(array $options)
    {
        require_once DRUPAL_ROOT . '/core/includes/update.inc';
        $pending = \update_get_update_list();

        $return = [];
        // Ensure system module's updates run first.
        $start['system'] = [];

        foreach ($pending as $module => $updates) {
            if (isset($updates['start'])) {
                foreach ($updates['pending'] as $update_id => $description) {
                    // Strip cruft from front.
                    $description = str_replace($update_id . ' -   ', '', $description);
                    $return[$module . "_update_$update_id"] = [
                        'module' => $module,
                        'update_id' => $update_id,
                        'description' => $description,
                        'type'=> 'hook_update_n'
                    ];
                }
                if (isset($updates['start'])) {
                    $start[$module] = $updates['start'];
                }
            }
        }

        // Append row(s) for pending entity definition updates.
        if ($options['entity-updates']) {
            foreach (\Drupal::entityDefinitionUpdateManager()
                         ->getChangeSummary() as $entity_type_id => $changes) {
                foreach ($changes as $change) {
                    $return[] = [
                        'module' => dt('@type entity type', ['@type' => $entity_type_id]),
                        'update_id' => '',
                        'description' => strip_tags($change),
                        'type' => 'entity-update'
                    ];
                }
            }
        }

        // Pending hook_post_update_X() implementations.
        $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateInformation();
        if ($options['post-updates']) {
            foreach ($post_updates as $module => $post_update) {
                foreach ($post_update as $key => $list) {
                    if ($key == 'pending') {
                        foreach ($list as $id => $item) {
                            $return[$module . '-post-' . $id] = [
                                'module' => $module,
                                'update_id' => $id,
                                'description' => $item,
                                'type' => 'post-update'
                            ];
                        }
                    }
                }
            }
        }

        return [$return, $start];
    }

    /**
     * Apply pending entity schema updates.
     */
    public function entityUpdatesMain()
    {
        $change_summary = \Drupal::entityDefinitionUpdateManager()->getChangeSummary();
        if (!empty($change_summary)) {
            $this->output()->writeln(dt('The following updates are pending:'));
            $this->io()->newLine();

            foreach ($change_summary as $entity_type_id => $changes) {
                $this->output()->writeln($entity_type_id . ' entity type : ');
                foreach ($changes as $change) {
                    $this->output()->writeln(strip_tags($change), 2);
                }
            }

            if (!$this->io()->confirm(dt('Do you wish to run all pending updates?'))) {
                throw new UserAbortException();
            }

            $operations[] = [[$this, 'updateEntityDefinitions'], []];


            $batch['operations'] = $operations;
            $batch += [
                'title' => 'Updating',
                'init_message' => 'Starting updates',
                'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
                'finished' => [$this, 'updateFinished'],
            ];
            batch_set($batch);

            // See updateFinished() for the restore of maint mode.
            $this->maintenanceModeOriginalState = \Drupal::service('state')->get('system.maintenance_mode');
            \Drupal::service('state')->set('system.maintenance_mode', true);
            drush_backend_batch_process();
        } else {
            $this->logger()->success(dt("No entity schema updates required"));
        }
    }

    /**
     * Log messages for any requirements warnings/errors.
     */
    public function updateCheckRequirements()
    {
        $return = true;

        \Drupal::moduleHandler()->resetImplementations();
        $requirements = update_check_requirements();
        $severity = drupal_requirements_severity($requirements);

        // If there are issues, report them.
        if ($severity != REQUIREMENT_OK) {
            if ($severity === REQUIREMENT_ERROR) {
                $return = false;
            }
            foreach ($requirements as $requirement) {
                if (isset($requirement['severity']) && $requirement['severity'] != REQUIREMENT_OK) {
                    $message = isset($requirement['description']) ? $requirement['description'] : '';
                    if (isset($requirement['value']) && $requirement['value']) {
                        $message .= ' (Currently using '. $requirement['title'] .' '. $requirement['value'] .')';
                    }
                    $log_level = $requirement['severity'] === REQUIREMENT_ERROR ? LogLevel::ERROR : LogLevel::WARNING;
                    $this->logger()->log($log_level, $message);
                }
            }
        }

        return $return;
    }
}
