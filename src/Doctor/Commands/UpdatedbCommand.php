<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Update\DatabaseUpdate;
use Drupal\Core\Update\UpdateServiceProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs under dr only. Drush keeps its own updatedb.
 *
 * dr boots the standard kernel, so this command switches to the container
 * update.php runs with (UpdateServiceProvider: null caches, a compiler pass
 * that tolerates missing services) by rebuilding the container itself, and
 * switches back at the end. Updates run in-process as a non-progressive batch.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Apply pending database updates. Enables maintenance mode during the run.',
    aliases: ['updb'],
    usages: ['updatedb --no-interaction'],
)]
final class UpdatedbCommand
{
    use UpdatedbTrait;

    public const string NAME = 'updatedb';

    /**
     * Key of the update provider in $GLOBALS['conf']['container_service_providers'].
     */
    private const string PROVIDER = 'drush_updatedb';

    /**
     * Functions that aborted, in order.
     *
     * @var string[]
     */
    private array $aborted = [];

    public function __construct(
        #[Autowire(service: 'kernel')]
        protected DrupalKernelInterface $kernel,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $container = $this->kernel->getContainer();
        $this->attachConsoleLogger($container, $output);
        $this->loadUpdateFiles();
        // Disables config overrides, as update.php does.
        if (!defined('MAINTENANCE_MODE')) {
            define('MAINTENANCE_MODE', 'update');
        }
        $logger = $this->updateLogger($container);

        if (!$this->checkRequirements($container, $logger)) {
            $io->error((string) $this->t('Requirements check reports errors. Resolve them and run updatedb again.'));
            return Command::FAILURE;
        }

        ['rows' => $rows, 'start' => $start] = $this->pendingUpdates($container, $logger);
        if (!$rows) {
            $io->success((string) $this->t('No database updates required.'));
            // Core flushes caches at the end of update.php even when nothing ran.
            drupal_flush_all_caches();
            return Command::SUCCESS;
        }
        $output->writeln($this->renderTable($rows), OutputInterface::OUTPUT_RAW);
        if (!$io->confirm((string) $this->t('Do you wish to run the specified pending updates?'))) {
            return Command::FAILURE;
        }

        $this->useUpdateContainer($output);
        $maintenanceMode = (bool) $this->container()->get('state')->get('system.maintenance_mode');
        try {
            $this->container()->get('state')->set('system.maintenance_mode', true);
            $this->runUpdates($start, $output);
        } finally {
            if (!$maintenanceMode) {
                $this->container()->get('state')->set('system.maintenance_mode', false);
            }
            unset($GLOBALS['conf']['container_service_providers'][self::PROVIDER]);
            // Rebuilds and caches a standard container compiled from the
            // updated code, which the update container must never be.
            drupal_flush_all_caches();
            $this->attachConsoleLogger($this->container(), $output);
        }
        $output->writeln(sprintf('Peak memory usage: %.1f MB', memory_get_peak_usage(true) / 1048576), OutputInterface::VERBOSITY_VERBOSE);

        if ($this->aborted) {
            $io->error((string) $this->t('Update aborted by: @functions', ['@functions' => implode(', ', $this->aborted)]));
            return Command::FAILURE;
        }
        $io->success((string) $this->t('Finished performing updates.'));
        return Command::SUCCESS;
    }

    /**
     * Logs requirement warnings and errors; returns FALSE when an error blocks
     * the update, as update.php does.
     */
    protected function checkRequirements(ContainerInterface $container, LoggerInterface $logger): bool
    {
        $ok = true;
        foreach ($container->get(DatabaseUpdate::class)->getRequirements() as $requirement) {
            $severity = $requirement['severity'] ?? RequirementSeverity::OK;
            if (is_int($severity)) {
                $severity = RequirementSeverity::from($severity);
            }
            if ($severity->value < RequirementSeverity::Warning->value) {
                continue;
            }
            $parts = array_filter([
                $this->plainText($requirement['value'] ?? '', $container),
                $this->plainText($requirement['description'] ?? '', $container),
            ]);
            $context = [
                '@title' => $this->plainText($requirement['title'] ?? '', $container),
                '@message' => implode(' ', $parts),
            ];
            if ($severity === RequirementSeverity::Error) {
                $ok = false;
                $logger->error('@title: @message', $context);
            } else {
                $logger->warning('@title: @message', $context);
            }
        }
        return $ok;
    }

    /**
     * Builds and runs the batch, as DbUpdateController::triggerBatch() does.
     *
     * @param array<string, int> $start
     *   The first hook_update_N to run, keyed by module.
     */
    protected function runUpdates(array $start, OutputInterface $output): void
    {
        $updates = update_resolve_dependencies($start);
        $dependencyMap = [];
        foreach ($updates as $function => $update) {
            $dependencyMap[$function] = empty($update['reverse_paths']) ? [] : array_keys($update['reverse_paths']);
        }

        $builder = (new BatchBuilder())->setTitle((string) $this->t('Updating'));
        $operations = 0;
        foreach ($updates as $function => $update) {
            if (!$update['allowed']) {
                continue;
            }
            // Set the installed version of each module so updates start at
            // the right place. The updates are sorted, so the first one seen
            // per module is its start.
            if (isset($start[$update['module']])) {
                $this->container()->get('update.update_hook_registry')->setInstalledVersion($update['module'], $update['number'] - 1);
                unset($start[$update['module']]);
            }
            $builder->addOperation($this->updateOperation(...), [$update['module'], $update['number'], $dependencyMap[$function], $output]);
            $operations++;
        }

        $postUpdates = $this->container()->get('update.post_update_registry')->getPendingUpdateFunctions();
        if ($postUpdates && $operations) {
            // Post updates see the code and services as left by the updates.
            $builder->addOperation($this->flushOperation(...), [$output]);
        }
        foreach ($postUpdates as $function) {
            $builder->addOperation($this->postUpdateOperation(...), [$function, $output]);
        }

        batch_set($builder->toArray());
        $batch = &batch_get();
        $batch['progressive'] = false;
        $this->ensureRoute($this->container());
        batch_process();
    }

    /**
     * Batch operation: runs one hook_update_N through core's DatabaseUpdate.
     */
    public function updateOperation(string $module, int $number, array $dependencyMap, OutputInterface $output, array &$context): void
    {
        $function = $module . '_update_' . $number;
        // doOne() returns early for an update that depends on an aborted one.
        if (!array_intersect($context['results']['#abort'] ?? [], [...$dependencyMap, $function])) {
            $this->logStart($function, $context);
        }
        $container = $this->container();
        try {
            $container->get(DatabaseUpdate::class)->doOne($module, $number, $dependencyMap, $context);
        } catch (\Throwable $e) {
            // doOne() catches and logs exceptions; errors reach here.
            $this->recordError($function, $e, $context);
        }
        $this->afterOperation($container, $output);
        $this->logResult($function, $context, $context['results'][$module][$number] ?? null);
    }

    /**
     * Batch operation: runs one hook_post_update_NAME().
     */
    public function postUpdateOperation(string $function, OutputInterface $output, array &$context): void
    {
        [$extension, $name] = explode('_post_update_', $function, 2);
        if (empty($context['results']['#abort'])) {
            $this->logStart($function, $context);
        }
        $container = $this->container();
        try {
            update_invoke_post_update($function, $context);
        } catch (\Throwable $e) {
            $this->recordError($function, $e, $context);
        }
        $this->afterOperation($container, $output);
        $this->logResult($function, $context, $context['results'][$extension][$name] ?? null);
    }

    /**
     * Batch operation: flushes caches between hook_update_N and post updates.
     */
    public function flushOperation(OutputInterface $output): void
    {
        $container = $this->container();
        drupal_flush_all_caches();
        $this->afterOperation($container, $output);
    }

    /**
     * Rebuilds the container with update.php's service provider.
     */
    protected function useUpdateContainer(OutputInterface $output): void
    {
        $GLOBALS['conf']['container_service_providers'][self::PROVIDER] = UpdateServiceProvider::class;
        $container = $this->container();
        $this->kernel->rebuildContainer();
        $this->afterOperation($container, $output);
    }

    /**
     * Keeps a rebuilt update container out of the site's container cache.
     *
     * The kernel caches every container it compiles. While the update provider
     * is registered that container must not be served to web requests, so the
     * cache entry is deleted whenever the container instance changed, which
     * also happens when an update installs a module. Console logging is
     * re-attached for the same reason.
     */
    protected function afterOperation(ContainerInterface $before, OutputInterface $output): void
    {
        $container = $this->container();
        if ($container !== $before) {
            $this->kernel->invalidateContainer();
            $this->attachConsoleLogger($container, $output);
        }
    }

    protected function logStart(string $function, array $context): void
    {
        if (empty($context['sandbox'])) {
            $this->updateLogger($this->container())->notice('Update started: @function', ['@function' => $function]);
        }
    }

    protected function recordError(string $function, \Throwable $e, array &$context): void
    {
        $this->updateLogger($this->container())->error('@message', ['@message' => $e->getMessage()]);
        $context['results']['#abort'][] = $function;
        $context['finished'] = 1;
    }

    /**
     * Logs the outcome of one pass of an operation.
     *
     * @param array|null $result
     *   The operation's entry in the batch results, or NULL when the worker
     *   returned early because an earlier update aborted.
     */
    protected function logResult(string $function, array $context, ?array $result): void
    {
        $logger = $this->updateLogger($this->container());
        $args = ['@function' => $function];
        // Checked before the result, which is also absent when the first pass
        // threw an error.
        if (in_array($function, $context['results']['#abort'] ?? [], true)) {
            // A failed pass returns no message; any stored one is left over
            // from an earlier pass and was already logged.
            if (!in_array($function, $this->aborted, true)) {
                $this->aborted[] = $function;
                $logger->error('Update failed: @function', $args);
            }
            return;
        }
        if ($result === null) {
            $logger->warning('Skipped @function: depends on an aborted update', $args);
            return;
        }
        if (!empty($result['results']['query'])) {
            $logger->notice('@message', ['@message' => $this->plainText($result['results']['query'])]);
        }
        if ($context['finished'] >= 1) {
            $logger->notice('Update completed: @function', $args);
        }
    }

    /**
     * The current container, which changes as the run rebuilds it.
     */
    protected function container(): ContainerInterface
    {
        return $this->kernel->getContainer();
    }
}
