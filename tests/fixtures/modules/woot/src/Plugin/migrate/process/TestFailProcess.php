<?php

declare(strict_types=1);

namespace Drupal\woot\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "test_fail"
 * )
 */
class TestFailProcess extends ProcessPluginBase
{
    /**
     * {@inheritdoc}
     */
    public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
    {
        // Fail only in MigrateRunnerTest::testMigrateImportAndRollback() test.
        // @see \Unish\MigrateRunnerTest::testMigrateImportAndRollback()
        if (in_array($value, [2, 9, 17]) && \Drupal::state()->get('woot.migrate_runner.trigger_failures')) {
            throw new MigrateException("ID {$value} should fail");
        }
        return $value;
    }
}
