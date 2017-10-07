<?php
namespace Drush\Commands\core;

use Drupal\Core\Utility\Error;
use Drupal\Core\Entity\EntityStorageException;
use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Log\LogLevel;

class UpdateDBCommands extends DrushCommands
{
    protected $cache_clear;

    /**
     * Apply any database updates required (as with running update.php).
     *
     * @command updatedb
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @option entity-updates Run automatic entity schema updates at the end of any update hooks. Defaults to disabled.
     * @bootstrap site
     * @aliases updb
     */
    public function updatedb($options = ['cache-clear' => true, 'entity-updates' => false])
    {
        $this->cache_clear = $options['cache-clear'];

        if (Drush::simulate()) {
            throw new \Exception('updatedb command does not support --simulate option.');
        }

        $result = $this->updateMain($options);
        if ($result === false) {
            throw new \Exception('Database updates not complete.');
        } elseif ($result > 0) {
            // Clear all caches in a new process. We just performed major surgery.
            drush_drupal_cache_clear_all();

            $this->logger()->success(dt('Finished performing updates.'));
        }
    }

    /**
     * Apply pending entity schema updates.
     *
     * @command entity:updates
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @bootstrap full
     * @aliases entup
     *
     */
    public function entityUpdates($options = ['cache-clear' => true])
    {
        if (Drush::simulate()) {
            throw new \Exception(dt('entity-updates command does not support --simulate option.'));
        }

        if ($this->entityUpdatesMain() === false) {
            throw new \Exception('Entity updates not run.');
        }

        drush_drupal_cache_clear_all();

        $this->logger()->success(dt('Finished performing updates.'));
    }

    /**
     * List any pending database updates.
     *
     * @command updatedb:status
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @option entity-updates Run automatic entity schema updates at the end of any update hooks. Defaults to --no-entity-updates.
     * @bootstrap full
     * @aliases updbst
     * @field-labels
     *   module: Module
     *   update_id: Update ID
     *   description: Description
     * @default-fields module,update_id,description
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function updatedbStatus($options = ['format'=> 'table'])
    {
        require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
        drupal_load_updates();
        list($pending, $start) = $this->getUpdatedbStatus();
        if (empty($pending)) {
            $this->logger()->success(dt("No database updates required"));
        } else {
            return new RowsOfFields($pending);
        }
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
     * @param $module
     *   The module whose update will be run.
     * @param $number
     *   The update number to run.
     * @param $context
     *   The batch context array
     */
    public function updateDoOne($module, $number, $dependency_map, &$context)
    {
        $function = $module . '_update_' . $number;

        // If this update was aborted in a previous step, or has a dependency that
        // was aborted in a previous step, go no further.
        if (!empty($context['results']['#abort']) && array_intersect($context['results']['#abort'], array_merge($dependency_map, array($function)))) {
            return;
        }

        $context['log'] = false;

        \Drupal::moduleHandler()->loadInclude($module, 'install');

        $ret = array();
        if (function_exists($function)) {
            try {
                if ($context['log']) {
                    Database::startLog($function);
                }

                $this->logger()->notice("Executing " . $function);
                $ret['results']['query'] = $function($context['sandbox']);
                $ret['results']['success'] = true;
            } // @TODO We may want to do different error handling for different exception
            // types, but for now we'll just print the message.
            catch (Exception $e) {
                $ret['#abort'] = array('success' => false, 'query' => $e->getMessage());
                $this->logger()->warning($e->getMessage());
            }

            if ($context['log']) {
                $ret['queries'] = Database::getLog($function);
            }
        } else {
            $ret['#abort'] = array('success' => false);
            $this->logger()->warning(dt('Update function @function not found', array('@function' => $function)));
        }

        if (isset($context['sandbox']['#finished'])) {
            $context['finished'] = $context['sandbox']['#finished'];
            unset($context['sandbox']['#finished']);
        }

        if (!isset($context['results'][$module])) {
            $context['results'][$module] = array();
        }
        if (!isset($context['results'][$module][$number])) {
            $context['results'][$module][$number] = array();
        }
        $context['results'][$module][$number] = array_merge($context['results'][$module][$number], $ret);

        if (!empty($ret['#abort'])) {
            // Record this function in the list of updates that were aborted.
            $context['results']['#abort'][] = $function;
        }

        // Record the schema update if it was completed successfully.
        if ($context['finished'] == 1 && empty($ret['#abort'])) {
            drupal_set_installed_schema_version($module, $number);
        }

        $context['message'] = 'Performing ' . $function;
    }

    public function updateMain($options)
    {
        // In D8, we expect to be in full bootstrap.
        drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_FULL);

        require_once DRUPAL_ROOT . '/core/includes/install.inc';
        require_once DRUPAL_ROOT . '/core/includes/update.inc';
        drupal_load_updates();
        update_fix_compatibility();

        // Pending hook_update_N() implementations.
        $pending = update_get_update_list();

        // Pending hook_post_update_X() implementations.
        $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateInformation();

        $start = array();

        $change_summary = [];
        if ($options['entity-updates']) {
            $change_summary = \Drupal::entityDefinitionUpdateManager()->getChangeSummary();
        }

        // Print a list of pending updates for this module and get confirmation.
        if (count($pending) || count($change_summary) || count($post_updates)) {
            $this->output()->writeln(dt('The following updates are pending:'));
            $this->io()->newLine();

            foreach ($change_summary as $entity_type_id => $changes) {
                $this->output()->writeln($entity_type_id . ' entity type : ');
                foreach ($changes as $change) {
                    $this->output()->writeln(strip_tags($change), 2);
                }
            }

            foreach (array('update', 'post_update') as $update_type) {
                $updates = $update_type == 'update' ? $pending : $post_updates;
                foreach ($updates as $module => $updates) {
                    if (isset($updates['start'])) {
                        $this->output()->writeln($module . ' module : ');
                        if (!empty($updates['pending'])) {
                            $start += [$module => array()];

                            $start[$module] = array_merge($start[$module], $updates['pending']);
                            foreach ($updates['pending'] as $update) {
                                $this->output()->writeln(strip_tags($update));
                            }
                        }
                        $this->io()->newLine();
                    }
                }
            }

            if (!$this->io()->confirm(dt('Do you wish to run all pending updates?'))) {
                throw new UserAbortException();
            }

            $this->updateBatch($options);
        } else {
            $this->logger()->success(dt("No database updates required"));
        }

        return count($pending) + count($change_summary) + count($post_updates);
    }

    /**
     * Start the database update batch process.
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
        $dependency_map = array();
        foreach ($updates as $function => $update) {
            $dependency_map[$function] = !empty($update['reverse_paths']) ? array_keys($update['reverse_paths']) : array();
        }

        $operations = array();

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
                $operations[] = array([$this, 'updateDoOne'], array($update['module'], $update['number'], $dependency_map[$function]));
            }
        }

        // Apply post update hooks.
        $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateFunctions();
        if ($post_updates) {
            $operations[] = [[$this, 'cacheRebuild'], []];
            foreach ($post_updates as $function) {
                $operations[] = ['update_invoke_post_update', [$function]];
            }
        }

        // Lastly, perform entity definition updates, which will update storage
        // schema if needed. If module update functions need to work with specific
        // entity schema they should call the entity update service for the specific
        // update themselves.
        // @see \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface::applyEntityUpdate()
        // @see \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface::applyFieldUpdate()
        if ($options['entity-updates'] &&  \Drupal::entityDefinitionUpdateManager()->needsUpdates()) {
            $operations[] = array([$this, 'updateEntityDefinitions'], array());
        }

        $batch['operations'] = $operations;
        $batch += array(
            'title' => 'Updating',
            'init_message' => 'Starting updates',
            'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
            'finished' => [$this, 'drush_update_finished'],
            'file' => 'core/includes/update.inc',
        );
        batch_set($batch);
        \Drupal::service('state')->set('system.maintenance_mode', true);
        drush_backend_batch_process();
        \Drupal::service('state')->set('system.maintenance_mode', false);
    }

    /**
     * Apply entity schema updates.
     */
    public function updateEntityDefinitions(&$context)
    {
        try {
            \Drupal::entityDefinitionUpdateManager()->applyUpdates();
        } catch (EntityStorageException $e) {
            watchdog_exception('update', $e);
            $variables = Error::decodeException($e);
            unset($variables['backtrace']);
            // The exception message is run through
            // \Drupal\Component\Utility\SafeMarkup::checkPlain() by
            // \Drupal\Core\Utility\Error::decodeException().
            $ret['#abort'] = array('success' => false, 'query' => t('%type: !message in %function (line %line of %file).', $variables));
            $context['results']['core']['update_entity_definitions'] = $ret;
            $context['results']['#abort'][] = 'update_entity_definitions';
        }
    }

    // Copy of protected \Drupal\system\Controller\DbUpdateController::getModuleUpdates.
    public function getUpdateList()
    {
        $return = array();
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
    public function cacheRebuild()
    {
        drupal_flush_all_caches();
        \Drupal::service('kernel')->rebuildContainer();
    }

    /**
     * Process and display any returned update output.
     *
     * @see \Drupal\system\Controller\DbUpdateController::batchFinished()
     * @see \Drupal\system\Controller\DbUpdateController::results()
     */
    public function updateFinished($success, $results, $operations)
    {

        if (!$this->cache_clear) {
            $this->logger()->info(dt("Skipping cache-clear operation due to --no-cache-clear option."));
        } else {
            drupal_flush_all_caches();
        }

        foreach ($results as $module => $updates) {
            if ($module != '#abort') {
                foreach ($updates as $number => $queries) {
                    foreach ($queries as $query) {
                        // If there is no message for this update, don't show anything.
                        if (empty($query['query'])) {
                            continue;
                        }

                        if ($query['success']) {
                            $this->logger()->notice(strip_tags($query['query']));
                        } else {
                            throw new \Exception('Failed: ' . strip_tags($query['query']));
                        }
                    }
                }
            }
        }
    }

    /**
     * Return a 2 item array with
     *  - an array where each item is a 3 item associative array describing a pending update.
     *  - an array listing the first update to run, keyed by module.
     */
    public function getUpdatedbStatus()
    {
        require_once DRUPAL_ROOT . '/core/includes/update.inc';
        $pending = \update_get_update_list();

        $return = array();
        // Ensure system module's updates run first.
        $start['system'] = array();

        foreach (\Drupal::entityDefinitionUpdateManager()->getChangeSummary() as $entity_type_id => $changes) {
            foreach ($changes as $change) {
                $return[] = array(
                'module' => dt('@type entity type', array('@type' => $entity_type_id)), 'update_id' => '', 'description' => strip_tags($change));
            }
        }

        // Print a list of pending updates for this module and get confirmation.
        foreach ($pending as $module => $updates) {
            if (isset($updates['start'])) {
                foreach ($updates['pending'] as $update_id => $description) {
                    // Strip cruft from front.
                    $description = str_replace($update_id . ' -   ', '', $description);
                    $return[] = array('module' => ucfirst($module), 'update_id' => $update_id, 'description' => $description);
                }
                if (isset($updates['start'])) {
                    $start[$module] = $updates['start'];
                }
            }
        }

        return array($return, $start);
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

            $operations[] = array([$this, 'updateEntityDefinitions'], array());


            $batch['operations'] = $operations;
            $batch += array(
                'title' => 'Updating',
                'init_message' => 'Starting updates',
                'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
                'finished' => [$this, 'updateFinished'],
            );
            batch_set($batch);
            \Drupal::service('state')->set('system.maintenance_mode', true);
            drush_backend_batch_process();
            \Drupal::service('state')->set('system.maintenance_mode', false);
        } else {
            $this->logger()->success(dt("No entity schema updates required"));
        }
    }
}
