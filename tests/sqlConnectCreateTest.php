<?php

namespace Unish;

/**
 * Tests sql-connect command
 *
 *   Installs Drupal and checks that the given URL by sql-connect is correct.
 *
 * @group commands
 * @group sql
 */
class SqlConnectCase extends CommandUnishTestCase {

  function testSqlConnect() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    // Get the connection details with sql-connect and check its structure.
    $this->drush('sql-connect', array(), $options);
    $connectionString = $this->getOutput();

    // Not all drivers need -e option like sqlite
    $shell_options = "-e";
    $db_driver = $this->db_driver();
    if ($db_driver == 'mysql') {
      $this->assertRegExp('/^mysql --user=[^\s]+ --password=.* --database=[^\s]+ --host=[^\s]+/', $connectionString);
    }
    elseif ($db_driver == 'sqlite') {
      $this->assertContains('sqlite3', $connectionString);
      $shell_options = '';
    }
    elseif ($db_driver == 'pgsql') {
      $this->assertRegExp('/^psql -q --dbname=[^\s]+ --host=[^\s]+ --port=[^\s]+ --username=[^\s]+/', $connectionString);
    }
    else {
      $this->markTestSkipped('sql-connect test does not recognize database type in ' . self::getDbUrl());
    }

    // Issue a query and check the result to verify the connection.
    $this->execute($connectionString . ' ' . $shell_options . ' "SELECT uid FROM users where uid = 1;"');
    $output = $this->getOutput();
    $this->assertContains('1', $output);

    // Run 'core-status' and insure that we can bootstrap Drupal.
    $this->drush('core-status', array(), $options + ['fields' => 'bootstrap']);
    $output = $this->getOutput();
    $this->assertContains('Successful', $output);

    // Test to see if 'sql-create' can erase the database.
    // The only output is a confirmation string, so we'll run
    // other commands to confirm that this worked.
    $this->drush('sql-create', array(), $options);

    // Try to execute a query.  This should give a "table not found" error.
    $this->execute($connectionString . ' ' . $shell_options . ' "SELECT uid FROM users where uid = 1;"', self::EXIT_ERROR);

    // We should still be able to run 'core-status' without getting an
    // error, although Drupal should not bootstrap any longer.
    $this->drush('core-status', array(), $options + ['fields' => 'bootstrap']);
    $output = $this->getOutput();
    $this->assertNotContains('Successful', $output);
  }
}
