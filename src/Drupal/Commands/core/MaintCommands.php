<?php

namespace Drush\Drupal\Commands\core;

    use Consolidation\AnnotatedCommand\CommandResult;
    use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
    use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
    use Drupal\Core\State\StateInterface;
    use Drush\Commands\DrushCommands;

class MaintCommands extends DrushCommands
{
    const KEY = 'system.maintenance_mode';

    protected $state;

    public function __construct(StateInterface $state)
    {
        $this->state = $state;
    }

    public function getState(): StateInterface
    {
        return $this->state;
    }

    /**
     * Get maintenance mode. Returns 1 if enabled, 0 if not.
     *
     * Consider using maint:status instead when chaining commands.
     *
     * @command maint:get
     *
     * @usage drush maint:get
     *   Print value of maintenance mode in Drupal
     * @aliases mget
     * @version 11.5
     */
    public function get(): string
    {
        $value = $this->getState()->get(self::KEY);
        return $value ? '1' : '0';
    }

    /**
     * Set maintenance mode.
     *
     * @command maint:set
     *
     * @param mixed $value The value to assign to the state key.
     * @usage drush maint:set 1
     *  Put site into Maintenance mode.
     * @usage drush maint:set 0
     *  Remove site from Maintenance mode.
     * @aliases mset
     * @version 11.5
     */
    public function set(string $value): void
    {
        $this->getState()->set(self::KEY, (bool) $value);
    }


    /**
     * Fail if maintenance mode is enabled.
     *
     * This commands fails with exit code of 3 when maintenance mode is on. This special
     * exit code distinguishes from a failure to complete.
     *
     * @command maint:status
     *
     * @usage drush maint:status && drush cron
     *   Only run cron when Drupal is not in maintenance mode.
     * @aliases mstatus
     * @version 11.5
     */
    public function status(): int
    {
        $value = $this->getState()->get(self::KEY);
        return $value ? self::EXIT_FAILURE_WITH_CLARITY : self::EXIT_SUCCESS;
    }
}
