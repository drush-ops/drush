<?php

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
        if ($value == 2 && \Drupal::state()->get('woot.test_migrate_import_and_rollback')) {
            throw new MigrateException('ID 2 should fail.');
        }
        return $value;
    }
}
