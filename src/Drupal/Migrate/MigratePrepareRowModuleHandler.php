<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Decorates the module handler to dispatch a Drush event on each source row.
 *
 * Injected into the migration's source plugin for the duration of the Drush
 * process. Replaces the former compile-time hook registration
 * (MigrateRunnerServiceProvider), which broke once Drupal 11.3 started
 * persisting hook lists in shared keyvalue storage: containers built by web
 * requests disagreed with containers built by Drush, causing either an
 * ArgumentCountError on web requests or silent data loss with
 * `migrate:import --delete`.
 *
 * @see \Drush\Drupal\Migrate\MigrateExecutable::interceptPrepareRow()
 * @see https://github.com/drush-ops/drush/issues/6595
 */
class MigratePrepareRowModuleHandler implements ModuleHandlerInterface
{
    public function __construct(
        protected readonly ModuleHandlerInterface $decorated,
        protected readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function invokeAll($hook, array $args = [])
    {
        if ($hook === 'migrate_prepare_row') {
            // Dispatch before delegating, matching the ordering of the hook
            // implementation this replaces. A MigrateSkipRowException thrown
            // by a listener propagates to SourcePluginBase::prepareRow().
            $this->eventDispatcher->dispatch(
                new MigratePrepareRowEvent($args[0], $args[1], $args[2]),
                MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW,
            );
        }
        return $this->decorated->invokeAll($hook, $args);
    }

    public function load($name)
    {
        return $this->decorated->load($name);
    }

    public function loadAll()
    {
        $this->decorated->loadAll();
    }

    public function isLoaded()
    {
        return $this->decorated->isLoaded();
    }

    public function reload()
    {
        $this->decorated->reload();
    }

    public function getModuleList()
    {
        return $this->decorated->getModuleList();
    }

    public function getModule($name)
    {
        return $this->decorated->getModule($name);
    }

    public function setModuleList(array $module_list = [])
    {
        $this->decorated->setModuleList($module_list);
    }

    public function addModule($name, $path)
    {
        $this->decorated->addModule($name, $path);
    }

    public function addProfile($name, $path)
    {
        $this->decorated->addProfile($name, $path);
    }

    public function buildModuleDependencies(array $modules)
    {
        return $this->decorated->buildModuleDependencies($modules);
    }

    public function moduleExists($module)
    {
        return $this->decorated->moduleExists($module);
    }

    public function loadAllIncludes($type, $name = null)
    {
        $this->decorated->loadAllIncludes($type, $name);
    }

    public function loadInclude($module, $type, $name = null)
    {
        return $this->decorated->loadInclude($module, $type, $name);
    }

    public function getHookInfo()
    {
        return $this->decorated->getHookInfo();
    }

    public function writeCache()
    {
        $this->decorated->writeCache();
    }

    public function resetImplementations()
    {
        $this->decorated->resetImplementations();
    }

    public function hasImplementations(string $hook, $modules = null): bool
    {
        return $this->decorated->hasImplementations($hook, $modules);
    }

    public function invokeAllWith(string $hook, callable $callback): void
    {
        $this->decorated->invokeAllWith($hook, $callback);
    }

    public function invoke($module, $hook, array $args = [])
    {
        return $this->decorated->invoke($module, $hook, $args);
    }

    public function invokeDeprecated($description, $module, $hook, array $args = [])
    {
        return $this->decorated->invokeDeprecated($description, $module, $hook, $args);
    }

    public function invokeAllDeprecated($description, $hook, array $args = [])
    {
        return $this->decorated->invokeAllDeprecated($description, $hook, $args);
    }

    public function alter($type, &$data, &$context1 = null, &$context2 = null)
    {
        $this->decorated->alter($type, $data, $context1, $context2);
    }

    public function alterDeprecated($description, $type, &$data, &$context1 = null, &$context2 = null)
    {
        $this->decorated->alterDeprecated($description, $type, $data, $context1, $context2);
    }

    public function getModuleDirectories()
    {
        return $this->decorated->getModuleDirectories();
    }

    public function getName($module)
    {
        return $this->decorated->getName($module);
    }
}
