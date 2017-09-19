<?php

/**
* @file
*  For now we only test sql-sync in simulated mode.
*
*  Future: Using two copies of Drupal, we could test
*  overwriting one site with another.
*/

namespace Unish;

/**
 *  @group slow
 *  @group commands
 *  @group sql
 */
class sqlSyncTest extends CommandUnishTestCase {

  /**
   * Covers the following responsibilities.
   *   - A user created on the source site is copied to the destination site.
   *   - The email address of the copied user is sanitized on the destination site.
   *
   * General handling of site aliases will be in sitealiasTest.php.
   */
  public function testLocalSqlSync() {
    $this->markTestSkipped('Depends on backend; also, some functions of sql-sync not implemented.');
    if ($this->db_driver() == 'sqlite') {
      $this->markTestSkipped('SQL Sync does not apply to SQLite.');
      return;
    }

    $sites = $this->setUpDrupal(2, TRUE);
    return $this->localSqlSync();
  }

  public function localSqlSync() {

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'stage',
      'yes' => NULL,
    );

    // Create a user in the staging site
    $name = 'joe.user';
    $mail = "joe.user@myhome.com";

    // Add user fields and a test User.
    $this->drush('pm-enable', array('field,text,telephone,comment'), $options + array('yes' => NULL));
    $this->drush('php-script', array(
      'user_fields-D' . UNISH_DRUPAL_MAJOR_VERSION,
      $name,
      $mail
      ), $options + array(
        'script-path' => __DIR__ . '/resources',
      )
    );

    // Copy stage to dev with --sanitize.
    $sync_options = array(
      'yes' => NULL,
      // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
      'structure-tables-list' => 'cache,cache*',
    );
    $this->drush('sql-sync', array('@stage', '@dev'), $sync_options);
    $this->drush('sql-sanitize', [], ['yes' => NULL], '@dev');

    // Confirm that the sample user is unchanged on the staging site
    $this->drush('user-information', array($name), $options + array('format' => 'csv', 'include-field-labels' => 0), '@stage');
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[0];
    $this->assertEquals($mail, $row[2], 'email address is unchanged on source site.');
    $this->assertEquals($name, $row[1]);

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );
    // Confirm that the sample user's email address has been sanitized on the dev site
    $this->drush('user-information', array($name), $options + array('format' => 'csv', 'include-field-labels' => 0));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[0];
    $this->assertEquals("user+$uid@localhost.localdomain", $row[2], 'email address was sanitized on destination site.');
    $this->assertEquals($name, $row[1]);

    // Copy stage to dev with --sanitize and a fixed sanitized email
    $sync_options = array(
      'yes' => NULL,
      // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
      'structure-tables-list' => 'cache,cache*',
    );
    $this->drush('sql-sync', array('@stage', '@dev'), $sync_options);
    $this->drush('sql-sanitize', [], ['yes' => NULL, 'sanitize-email' => 'user@mysite.org'], '@dev');

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );
    // Confirm that the sample user's email address has been sanitized on the dev site
    $this->drush('user-information', array($name), $options + array('format' => 'csv', 'include-field-labels' => 0));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[0];
    $this->assertEquals("user@mysite.org", $row[2], 'email address was sanitized (fixed email) on destination site.');
    $this->assertEquals($name, $row[1]);


    $fields = [
      'field_user_email' => 'joe.user.alt@myhome.com',
      'field_user_string' => 'Private info',
      'field_user_string_long' => 'Really private info',
      'field_user_text' => 'Super private info',
      'field_user_text_long' => 'Super duper private info',
      'field_user_text_with_summary' => 'Private',
    ];
    // Assert that field DO NOT contain values.
    foreach ($fields as $field_name => $value) {
      $this->assertUserFieldContents($field_name, $value, $options);
    }

    // Assert that field_user_telephone DOES contain "5555555555".
    $this->assertUserFieldContents('field_user_telephone', '5555555555', $options, TRUE);
  }

  /**
   * Assert that a field on the user entity does or does not contain a value.
   *
   * @param string $field_name
   *   The machine name of the field.
   * @param string $value
   *   The field value.
   * @param array $options
   *   Options to be added to the sql-query command.
   * @param bool $should_contain
   *   Whether the field should contain the value. Defaults to false.
   */
  public function assertUserFieldContents($field_name, $value, $options = [], $should_contain = FALSE) {
    $table = 'user__' . $field_name;
    $column = $field_name . '_value';
    $this->drush('sql-query', [ "SELECT $column FROM $table LIMIT 1" ], $options);
    $output = $this->getOutput();
    $this->assertNotEmpty($output);

    if ($should_contain) {
      $this->assertContains($value, $output);
    }
    else {
      $this->assertNotContains($value, $output);
    }
  }
}
