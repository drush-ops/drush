<?php

namespace Drupal\Tests\Core\Test;

use Drupal\Tests\UnitTestCase;
use Drupal\deprecation_test\Deprecation\FixtureDeprecatedClass;

/**
 * Test how unit tests interact with deprecation errors in process isolation.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group Test
 * @group legacy
 */
class PhpUnitBridgeIsolatedTest extends UnitTestCase {

  /**
   * @expectedDeprecation Drupal\deprecation_test\Deprecation\FixtureDeprecatedClass is deprecated.
   */
  public function testDeprecatedClass() {
    $deprecated = new FixtureDeprecatedClass();
    $this->assertEquals('test', $deprecated->testFunction());
  }

}
