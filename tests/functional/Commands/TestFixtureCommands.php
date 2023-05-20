<?php

declare(strict_types=1);

/**
 * @file
 *   Commands which are useful for unit tests.
 */

namespace Drush\Commands;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drush\Drush;
use Psr\Container\ContainerInterface as DrushContainer;

class TestFixtureCommands extends DrushCommands
{
    protected function __construct(
        private $autoloader
    ) {
        parent::__construct();
    }

    public static function createEarly(DrushContainer $drush_container): self
    {
        $commandHandler = new static(
            $drush_container->get('loader')
        );

        return $commandHandler;
    }

  // Obsolete:
  //   unit-invoke
  //   missing-callback
  //
  // Future:
  //   unit-drush-dependency: Command depending on an unknown commandfile

  /**
   * No-op command, used to test completion for commands that start the same as other commands.
   * We don't do completion in Drush core any longer, but keeping as a placeholder for now.
   *
   * @command unit
   */
    public function unit()
    {
    }

  /**
   * Works like php-eval. Used for testing $command_specific context.
   *
   * @command unit-eval
   * @bootstrap max
   */
    public function drushUnitEval($code)
    {
        return eval($code . ';');
    }

  /**
   * Run a batch process.
   *
   * @command unit-batch
   * @bootstrap max
   */
    public function drushUnitBatch()
    {
        // Reduce php memory/time limits to test respawn.
        // TODO.

        $operations[] = ['\Unish\Batch\UnitBatchOperations::operate', []];

        $batch = [
            'operations' => $operations,
            'finished' => '\Unish\Batch\UnitBatchOperations::finish',
        ];
        \batch_set($batch);
        $result = \drush_backend_batch_process();
    }

  /**
   * Return options as function result.
   * @command unit-return-options
   */
    public function drushUnitReturnOptions($arg = '', $options = ['x' => 'y', 'data' => [], 'format' => 'yaml'])
    {
        unset($options['format']);
        return $options;
    }

  /**
   * Return original argv as function result.
   * @command unit-return-argv
   */
    public function drushUnitReturnArgv(array $args)
    {
        return $args;
    }

    /**
     * Clears the dependency injection container.
     *
     * Intended for testing cases that require the container to be rebuilt from
     * scratch.
     *
     * @command unit-invalidate-container
     * @bootstrap site
     */
    public function drushUnitInvalidateContainer()
    {
        $request = Drush::bootstrap()->getRequest();
        $sitePath = DrupalKernel::findSitePath($request);

        // Perform early bootstrap. This includes dynamic configuration of PHP,
        // setting the error and exception handlers etc.
        DrupalKernel::bootEnvironment();

        // Initialize database connections and apply configuration from
        // settings.php.
        Settings::initialize(DRUPAL_ROOT, $sitePath, $this->autoloader);

        $kernel = new DrupalKernel('prod', $autoloader);
        $kernel->setSitePath($sitePath);

        // We need to boot the kernel in order to load the service that can
        // delete the compiled container from the cache backend.
        $kernel->boot();
        $kernel->invalidateContainer();
    }
}
