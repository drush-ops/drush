<?php

/**
 * @file
 *   Commands which are useful for unit tests.
 */
namespace Drush\Commands;

class TestFixtureCommands
{
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
        // Reduce php memory/time limits to test backend respawn.
        // TODO.

        $batch = [
        'operations' => [
         [[$this, '_drushUnitBatchOperation'], []],
        ],
        'finished' => [$this, '_drushUnitBatchFinished'],
        // 'file' => Doesn't work for us. Drupal 7 enforces this path
        // to be relative to DRUPAL_ROOT.
        // @see _batch_process().
        ];
        \batch_set($batch);
        \drush_backend_batch_process();

        // Print the batch output.
        \drush_backend_output();
    }

    public function _drushUnitBatchOperation(&$context)
    {
        $context['message'] = "!!! ArrayObject does its job.";

        for ($i = 0; $i < 5; $i++) {
            \drush_print("Iteration $i");
        }
        $context['finished'] = 1;
    }

    public function _drushUnitBatchFinished()
    {
        // Restore php limits.
        // TODO.
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
