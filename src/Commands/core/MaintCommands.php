<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

final class MaintCommands extends DrushCommands
{
    use AutowireTrait;

    const KEY = 'system.maintenance_mode';
    const GET = 'maint:get';
    const SET = 'maint:set';
    const STATUS = 'maint:status';

    public function __construct(protected StateInterface $state)
    {
    }

    public function getState(): StateInterface
    {
        return $this->state;
    }

    /**
     * Get maintenance mode. Returns 1 if enabled, 0 if not.
     *
     * Consider using maint:status instead when chaining commands.
     */
    #[CLI\Command(name: self::GET, aliases: ['mget'])]
    #[CLI\Usage(name: 'drush maint:get', description: 'Print value of maintenance mode in Drupal')]
    #[CLI\Version(version: '11.5')]
    public function get(): string
    {
        $value = $this->getState()->get(self::KEY);
        return $value ? '1' : '0';
    }

    /**
     * Set maintenance mode.
     */
    #[CLI\Command(name: self::SET, aliases: ['mset'])]
    #[CLI\Argument(name: 'value', description: 'The value to assign to the state key', suggestedValues: ['0', '1'])]
    #[CLI\Usage(name: 'drush maint:set 1', description: 'Put site into Maintenance mode.')]
    #[CLI\Usage(name: 'drush maint:set 0', description: 'Remove site from Maintenance mode.')]
    #[CLI\Version(version: '11.5')]
    public function set(string $value): void
    {
        $this->getState()->set(self::KEY, (bool) $value);
    }


    /**
     * Fail if maintenance mode is enabled.
     *
     * This commands fails with exit code of 3 when maintenance mode is on. This special
     * exit code distinguishes from a failure to complete.
     */
    #[CLI\Command(name: self::STATUS, aliases: ['mstatus'])]
    #[CLI\Usage(name: 'drush maint:status && drush cron', description: 'Only run cron when Drupal is not in maintenance mode.')]
    #[CLI\Version(version: '11.5')]
    public function status(): int
    {
        $value = $this->getState()->get(self::KEY);
        return $value ? self::EXIT_FAILURE_WITH_CLARITY : self::EXIT_SUCCESS;
    }
}
