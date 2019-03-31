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

class FixtureCommands extends DrushCommands
{
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
}
