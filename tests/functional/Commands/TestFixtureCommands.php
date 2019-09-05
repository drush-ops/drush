<?php

/**
 * @file
 *   Commands which are useful for unit tests.
 */
namespace Drush\Commands;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Drush;

class TestFixtureCommands extends DrushCommands implements AutoloaderAwareInterface
{

    use AutoloaderAwareTrait;

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
        $autoloader = $this->loadDrupalAutoloader(DRUPAL_ROOT);
        $request = Drush::bootstrap()->getRequest();
        $sitePath = DrupalKernel::findSitePath($request);

        // Perform early bootstrap. This includes dynamic configuration of PHP,
        // setting the error and exception handlers etc.
        DrupalKernel::bootEnvironment();

        // Initialize database connections and apply configuration from
        // settings.php.
        Settings::initialize(DRUPAL_ROOT, $sitePath, $autoloader);

        $kernel = new DrupalKernel('prod', $autoloader);
        $kernel->setSitePath($sitePath);

        // We need to boot the kernel in order to load the service that can
        // delete the compiled container from the cache backend.
        $kernel->boot();
        $kernel->invalidateContainer();
    }

    /**
     * Loads the Drupal autoloader and returns the instance.
     *
     * @see \Drush\Commands\core\CacheCommands::loadDrupalAutoloader()
     */
    protected function loadDrupalAutoloader($drupal_root)
    {
        static $autoloader = false;

        $autoloadFilePath = $drupal_root .'/autoload.php';
        if (!$autoloader && file_exists($autoloadFilePath)) {
            $autoloader = require $autoloadFilePath;
        }

        if ($autoloader === true) {
            // The autoloader was already required. Assume that Drush and Drupal share an autoloader per
            // "Point autoload.php to the proper vendor directory" - https://www.drupal.org/node/2404989
            $autoloader = $this->autoloader();
        }

        return $autoloader;
    }
}
