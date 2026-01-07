<?php

declare(strict_types=1);

namespace Drush\Commands\updatedb;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Update\EquivalentUpdate;
use Drupal\Core\Utility\Error;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Boot\Kernels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Drupal\DrupalUtil;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Apply any pending database updates. Automatically enables maintenance mode during the update.',
    aliases: ['updb'],
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
final class UpdateDBCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'updatedb';

    /**
     * Note - can't inject @database since a method below is static.
     */
    public function __construct(
        protected BootstrapManager $bootstrapManager,
        protected readonly LoggerInterface $logger,
        protected readonly ProcessManager $processManager,
        protected readonly SiteAliasManagerInterface $siteAliasManager,
        protected DrushConfig $drushConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('cache-clear', mode: InputOption::VALUE_REQUIRED, description: 'Clear caches upon completion.', default: '1')
            ->addOption('force', mode: InputOption::VALUE_NONE, description: 'Report requirements errors, but don\'t stop processing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        // Do own bootstrap so we can use the 'update' kernel. Replaces the [#Kernel] attribute.
        $annotationData = new AnnotationData(['kernel' => Kernels::UPDATE]);
        $this->bootstrapManager->bootstrapToPhaseIndex(DrupalBootLevels::FULL, $annotationData);

        require_once DRUPAL_ROOT . '/core/includes/install.inc';
        require_once DRUPAL_ROOT . '/core/includes/update.inc';
        drupal_load_updates();

        // Disables extensions that have a lower Drupal core major version, or too high of a PHP requirement.
        // Those are rare, and this function does a full rebuild. So commenting it out for now.
        // update_fix_compatibility();

        // Check requirements before updating.
        if (!$this->updateCheckRequirements() && !$input->getOption('force')) {
            throw new RuntimeException('Requirements check reports errors. Use --force to bypass.');
        }

        $status_options = ['strict' => 0];
        $status_options = array_merge(Drush::redispatchOptions(), $status_options);

        $process = $this->processManager->drush($this->siteAliasManager->getSelf(), UpdateDbStatusCommand::NAME, [], $status_options);
        $process->mustRun();
        if ($process->getOutput()) {
            // We have pending updates - let's run em.
            $output->writeln($process->getOutput());
            if (!$io->confirm(dt('Do you wish to run the specified pending updates?'))) {
                throw new UserAbortException();
            }
            $success = $this->drushConfig->simulate() ? true : $this->updateBatch();

            if ($success) {
                $io->success('Finished performing updates.');
            } else {
                $this->logger->error('Finished performing updates.');
            }
        } else {
            $io->success('No pending updates.');
            $success = true;
        }
        // Flush all caches regardless of whether updates ran. When Drupal
        // core performs database updates it also clears the cache at the
        // end. This ensures that we are compatible with updates that rely
        // on this behavior.
        if ($input->getOption('cache-clear')) {
            drupal_flush_all_caches();
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Log messages for any requirements warnings/errors.
     */
    public function updateCheckRequirements(): bool
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
                    $message = isset($requirement['description']) ? DrupalUtil::drushRender($requirement['description']) : '';
                    if (isset($requirement['value']) && $requirement['value']) {
                        $message .= ' (Currently using ' . $requirement['title'] . ' ' . DrupalUtil::drushRender($requirement['value']) . ')';
                    }
                    $log_level = $requirement['severity'] === REQUIREMENT_ERROR ? LogLevel::ERROR : LogLevel::WARNING;
                    $this->logger->log($log_level, $message);
                }
            }
        }

        return $return;
    }

    /**
     * Start the database update batch process.
     */
    public function updateBatch(): bool
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
            $dependency_map[$function] = empty($update['reverse_paths']) ? [] : array_keys($update['reverse_paths']);
        }

        $operations = [];

        foreach ($updates as $update) {
            if ($update['allowed']) {
                // Set the installed version of each module so updates will start at the
                // correct place. (The updates are already sorted, so we can simply base
                // this on the first one we come across in the above foreach loop.)
                if (isset($start[$update['module']])) {
                    \Drupal::service("update.update_hook_registry")->setInstalledVersion($update['module'], $update['number'] - 1);
                    unset($start[$update['module']]);
                }
                // Add this update function to the batch.
                $function = $update['module'] . '_update_' . $update['number'];
                $operations[] = [self::class . '::updateDoOne', [$update['module'], $update['number'], $dependency_map[$function]]];
            }
        }

        // Lastly, apply post update hooks.
        $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateFunctions();
        if ($post_updates) {
            if ($operations) {
                // Only needed if we performed updates earlier.
                $operations[] = [self::class . '::cacheRebuild', []];
            }
            foreach ($post_updates as $function) {
                $operations[] = [self::class . '::updateDoOnePostUpdate', [$function]];
            }
        }

        $original_maint_mode = \Drupal::service('state')->get('system.maintenance_mode');
        if (!$original_maint_mode) {
            \Drupal::service('state')->set('system.maintenance_mode', true);
            $operations[] = [self::class . '::restoreMaintMode', [false]];
        }

        $batch['operations'] = $operations;
        $batch += [
            'title' => 'Updating',
            'init_message' => 'Starting updates',
            'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
            // To record results, we currently need a callback, even if it does nothing.
            'finished' => UpdateDBCommand::class . '::updateFinished',
            'file' => 'core/includes/update.inc',
        ];
        batch_set($batch);
        $result = drush_backend_batch_process(UpdateDbBatchProcessCommand::NAME);

        $success = false;
        if (!is_array($result)) {
            $this->logger->error(dt('Batch process did not return a result array. Returned: !type', ['!type' => gettype($result)]));
        } elseif (!empty($result[0]['#abort'])) {
            // Whenever an error occurs the batch process does not continue, so
            // this array should only contain a single item, but we still output
            // all available data for completeness.
            $this->logger->error(dt('Update aborted by: !process', [
                '!process' => implode(', ', $result[0]['#abort']),
            ]));
        } else {
            $success = true;
        }

        return $success;
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
     * @param array $context
     *   The batch context object.
     */
    public static function updateDoOne(string $module, int $number, array $dependency_map, array $context): void
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
        $equivalent_update = \Drupal::service('update.update_hook_registry')->getEquivalentUpdate($module, $number);
        if ($equivalent_update instanceof EquivalentUpdate) {
            $ret['results']['query'] = $equivalent_update->toSkipMessage();
            $ret['results']['success'] = true;
            $context['sandbox']['#finished'] = true;
        } elseif (function_exists($function)) {
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
            }

            if ($context['log']) {
                $ret['queries'] = Database::getLog($function);
            }
        } else {
            $ret['#abort'] = ['success' => false];
            Drush::logger()->warning(dt('Update function @function not found in file @filename', [
                '@function' => $function,
                '@filename' => "$module.install",
            ]));
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
            Drush::logger()->error("Update failed: $function");
        }

        // Record the schema update if it was completed successfully.
        if ($context['finished'] >= 1 && empty($ret['#abort'])) {
            \Drupal::service("update.update_hook_registry")->setInstalledVersion($module, $number);
            $context['message'] = "Update completed: $function";
        }
    }

    /**
     * Batch command that executes a single post-update.
     *
     * @param string $function
     *   The post-update function to execute.
     *   The batch context object.
     */
    public static function updateDoOnePostUpdate(string $function, array $context): void
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

        [$extension, $name] = explode('_post_update_', $function, 2);
        \Drupal::service('update.post_update_registry')->getUpdateFunctions($extension);

        if (function_exists($function)) {
            if (empty($context['results'][$extension][$name]['type'])) {
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
        } else {
            $ret['#abort'] = ['success' => false];
            Drush::logger()->warning(dt('Post update function @function not found.', [
                '@function' => $function
            ]));
        }

        if (isset($context['sandbox']['#finished'])) {
            $context['finished'] = $context['sandbox']['#finished'];
            unset($context['sandbox']['#finished']);
        }
        if (!isset($context['results'][$extension][$name])) {
            $context['results'][$extension][$name] = [];
        }
        $context['results'][$extension][$name] = array_merge($context['results'][$extension][$name], $ret);

        // Log the message that was returned.
        if (!empty($ret['results']['query'])) {
            Drush::logger()->notice(strip_tags((string) $ret['results']['query']));
        }

        if (!empty($ret['#abort'])) {
            // Record this function in the list of updates that were aborted.
            $context['results']['#abort'][] = $function;
            Drush::logger()->error("Update failed: $function");
        } elseif ($context['finished'] == 1 && empty($ret['#abort'])) {
            $context['message'] = "Update completed: $function";
        }
    }

    /**
     * Batch finished callback.
     *
     * @param boolean $success Whether the batch ended without a fatal error.
     */
    public static function updateFinished(bool $success, array $results, array $operations): void
    {
        // No longer used. Flush moved to \Drush\Commands\core\UpdateDBCommands::updatedb.
    }

    public static function restoreMaintMode($status): void
    {
        \Drupal::service('state')->set('system.maintenance_mode', $status);
    }

    // Copy of protected \Drupal\system\Controller\DbUpdateController::getModuleUpdates.
    public function getUpdateList(): array
    {
        $return = [];
        $updates = update_get_update_list();
        foreach ($updates as $module => $update) {
            if (!empty($update['start'])) {
                $return[$module] = $update['start'];
            }
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
     * @see \Drupal\system\Controller\DbUpdateController::triggerBatch()
     */
    public static function cacheRebuild(): void
    {
        drupal_flush_all_caches();
        \Drupal::service('kernel')->rebuildContainer();
        // Load the module data which has been removed when the container was
        // rebuilt.
        $module_handler = \Drupal::moduleHandler();
        $module_handler->loadAll();
        $module_handler->invokeAll('rebuild');
    }
}
