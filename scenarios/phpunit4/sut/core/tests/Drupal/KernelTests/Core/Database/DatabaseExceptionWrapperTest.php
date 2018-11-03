<?php

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests exceptions thrown by queries.
 *
 * @group Database
 */
class DatabaseExceptionWrapperTest extends KernelTestBase {

  /**
   * Tests the expected database exception thrown for prepared statements.
   */
  public function testPreparedStatement() {
    $connection = Database::getConnection();
    try {
      // SQLite validates the syntax upon preparing a statement already.
      // @throws \PDOException
      $query = $connection->prepare('bananas');

      // MySQL only validates the syntax upon trying to execute a query.
      // @throws \Drupal\Core\Database\DatabaseExceptionWrapper
      $connection->query($query);

      $this->fail('Expected PDOException or DatabaseExceptionWrapper, none was thrown.');
    }
    catch (\PDOException $e) {
      $this->pass('Expected PDOException was thrown.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->pass('Expected DatabaseExceptionWrapper was thrown.');
    }
    catch (\Exception $e) {
      $this->fail("Thrown exception is not a PDOException:\n" . (string) $e);
    }
  }

  /**
   * Tests the expected database exception thrown for inexistent tables.
   */
  public function testQueryThrowsDatabaseExceptionWrapperException() {
    $connection = Database::getConnection();
    try {
      $connection->query('SELECT * FROM {does_not_exist}');
      $this->fail('Expected PDOException, none was thrown.');
    }
    catch (DatabaseExceptionWrapper $e) {
      $this->pass('Expected DatabaseExceptionWrapper was thrown.');
    }
    catch (\Exception $e) {
      $this->fail("Thrown exception is not a DatabaseExceptionWrapper:\n" . (string) $e);
    }
  }

}
