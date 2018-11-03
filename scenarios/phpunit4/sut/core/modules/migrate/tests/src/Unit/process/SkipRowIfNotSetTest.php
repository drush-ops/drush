<?php

namespace Drupal\Tests\migrate\Unit\process;

use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\process\SkipRowIfNotSet;

/**
 * Tests the skip row if not set process plugin.
 *
 * @group migrate
 * @coversDefaultClass \Drupal\migrate\Plugin\migrate\process\SkipRowIfNotSet
 */
class SkipRowIfNotSetTest extends MigrateProcessTestCase {

  /**
   * Tests that a skip row exception without a message is raised.
   *
   * @covers ::transform
   */
  public function testRowSkipWithoutMessage() {
    $configuration = [
      'index' => 'some_key',
    ];
    $process = new SkipRowIfNotSet($configuration, 'skip_row_if_not_set', []);
    $this->setExpectedException(MigrateSkipRowException::class);
    $process->transform('', $this->migrateExecutable, $this->row, 'destinationproperty');
  }

  /**
   * Tests that a skip row exception with a message is raised.
   *
   * @covers ::transform
   */
  public function testRowSkipWithMessage() {
    $configuration = [
      'index' => 'some_key',
      'message' => "The 'some_key' key is not set",
    ];
    $process = new SkipRowIfNotSet($configuration, 'skip_row_if_not_set', []);
    $this->setExpectedException(MigrateSkipRowException::class, "The 'some_key' key is not set");
    $process->transform('', $this->migrateExecutable, $this->row, 'destinationproperty');
  }

}
