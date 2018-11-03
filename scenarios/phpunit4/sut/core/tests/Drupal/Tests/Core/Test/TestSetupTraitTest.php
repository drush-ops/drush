<?php

namespace Drupal\Tests\Core\Test;

use Drupal\Core\Database\Database;
use Drupal\Core\Test\TestSetupTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the TestSetupTrait trait.
 *
 * @coversDefaultClass \Drupal\Core\Test\TestSetupTrait
 * @group Testing
 *
 * Run in a separate process as this test involves Database statics and
 * environment variables.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TestSetupTraitTest extends UnitTestCase {

  /**
   * Tests the SIMPLETEST_DB environment variable is used.
   *
   * @covers ::changeDatabasePrefix
   */
  public function testChangeDatabasePrefix() {
    putenv('SIMPLETEST_DB=pgsql://user:pass@127.0.0.1/db');
    $connection_info = Database::convertDbUrlToConnectionInfo('mysql://user:pass@localhost/db', '');
    Database::addConnectionInfo('default', 'default', $connection_info);
    $this->assertEquals('mysql', Database::getConnectionInfo()['default']['driver']);
    $this->assertEquals('localhost', Database::getConnectionInfo()['default']['host']);

    // Create a mock for testing the trait and set a few properties that are
    // used to avoid unnecessary set up.
    $test_setup = $this->getMockForTrait(TestSetupTrait::class);
    $test_setup->databasePrefix = 'testDbPrefix';
    $test_setup->root = '';

    $method = new \ReflectionMethod(get_class($test_setup), 'changeDatabasePrefix');
    $method->setAccessible(TRUE);
    $method->invoke($test_setup);

    // Ensure that SIMPLETEST_DB defines the default database connection after
    // calling \Drupal\Core\Test\TestSetupTrait::changeDatabasePrefix().
    $this->assertEquals('pgsql', Database::getConnectionInfo()['default']['driver']);
    $this->assertEquals('127.0.0.1', Database::getConnectionInfo()['default']['host']);
  }

}
