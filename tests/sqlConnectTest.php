<?php

/**
 * @file
 *   Tests sql-connect command
 *
 *   Installs Drupal and checks that the given URL by sql-connect is correct.
 *
 * @group commands
 */
class SqlConnectCase extends Drush_CommandTestCase {

  function testSqlConnect() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    // Get the connection details with sql-connect and check its structure.
    $this->drush('sql-connect', array(), $options);
    $output = $this->getOutput();

    // Not all drivers need -e option like sqlite
    $shell_options = "-e";
    $db_driver = $this->db_driver();
    if ($db_driver == 'mysql') {
      $this->assertRegExp('/^mysql --database=[^\s]+ --host=[^\s]+ --user=[^\s]+ --password=.*$/', $output);
    }
    elseif ($db_driver == 'sqlite') {
      $this->assertContains('sqlite3', $output);
      $shell_options = '';
    }
    elseif ($db_driver == 'pgsql') {
      $this->assertRegExp('/^psql --dbname=[^\s]+ --host=[^\s]+ --port=[^\s] --username=[^\s]+/', $output);
    }
    else {
      $this->markTestSkipped('sql-connect test does not recognize database type in ' . UNISH_DB_URL);
    }

    // Issue a query and check the result to verify the connection.
    $this->execute($output . ' ' . $shell_options . ' "select name from users where uid = 1;"');
    $output = $this->getOutput();
    $this->assertContains('admin', $output);

  }
}
