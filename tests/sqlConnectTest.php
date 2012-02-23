<?php

/*
 * @file
 *   Tests sql-connect command
 *
 *   Installs Drupal and checks that the given URL by sql-connect is correct.
 *   @TODO: test Postgre-SQL and Sqlite.
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
    $this->assertRegExp('/^mysql --database=[^\s]+ --host=[^\s]+ --user=[^\s]+ --password=.*$/', $output);

    // Issue a query and check the result to verify the connection.
    $this->execute($output . ' -e "select name from users where uid = 1;"');
    $output = $this->getOutput();
    $this->assertContains('admin', $output);
  }
}
