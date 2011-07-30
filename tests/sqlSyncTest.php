<?php

/*
* @file
*  For now we only test sql-sync in simulated mode.
*
*  Future: Using two copies of Drupal, we could test
*  overwriting one site with another.
*/

class sqlSyncTest extends Drush_CommandTestCase {

  /*
   * Covers the following responsibilities.
   *   - A user created on the source site is copied to the destination site.
   *   - The email address of the copied user is sanitized on the destination site.
   *
   * General handling of site aliases will be in sitealiasTest.php.
   */
  public function testLocalSqlSync() {
    if (strpos(UNISH_DB_URL, 'sqlite') !== FALSE) {
      $this->markTestSkipped('SQL Sync does not apply to SQLite.');
      return;
    }

    $sites = $this->setUpDrupal(2, TRUE);
    $dump_dir = UNISH_SANDBOX . "/dump-dir";
    mkdir($dump_dir);

    // Create a user in the staging site
    $name = 'joe.user';
    $mail = "joe.user@myhome.com";
    $options = array(
      'root' => $this->webroot(),
      'uri' => 'stage',
      'yes' => NULL,
    );
    $this->drush('user-create', array($name), $options + array('password' => 'password', 'mail' => $mail));

    // Copy stage to dev with --sanitize
    $sync_options = array(
      'sanitize' => NULL,
      'yes' => NULL,
      'dump-dir' => $dump_dir
    );
    $this->drush('sql-sync', array('@stage', '@dev'), $sync_options);

    // Confirm that the sample user has the correct email address on the staging site
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[1];
    $this->assertEquals($mail, $row[2], 'email address is unchanged on source site.');
    $this->assertEquals($name, $row[0]);

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );
    // Confirm that the sample user's email address has been sanitized on the dev site
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[1];
    $this->assertEquals("user+2@localhost", $row[2], 'email address was sanitized on destination site.');
    $this->assertEquals($name, $row[0]);
  }
}
