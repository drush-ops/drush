<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\StorageInterface;

/**
 * Lets a test class that shares its environment change configuration.
 *
 * Call ::saveInitialConfig() at the end of ::setUpEnvironment(), and
 * ::restoreInitialConfig() in ::resetEnvironment().
 */
trait RestoresConfigTrait
{
    /**
     * Records the active configuration as the one every test method starts with.
     */
    protected function saveInitialConfig(): void
    {
        $this->copyConfig($this->container->get('config.storage'), $this->initialConfigStorage());
    }

    /**
     * Undoes the configuration changes of a test method.
     */
    protected function restoreInitialConfig(): void
    {
        $this->copyConfig($this->initialConfigStorage(), $this->container->get('config.storage'));
    }

    /**
     * A table of its own: core's snapshot storage is rewritten by every import.
     */
    private function initialConfigStorage(): StorageInterface
    {
        return new DatabaseStorage($this->container->get('database'), 'unish_config_initial');
    }
}
