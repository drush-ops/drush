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
    if ($this->db_driver() == 'sqlite') {
      $this->markTestSkipped('SQL Sync does not apply to SQLite.');
      return;
    }

    $sites = $this->setUpDrupal(2, TRUE);
    return $this->localSqlSync();
  }
  /**
   * Do the same test as above, but use Drupal 6 sites instead of Drupal 7.
   */
  public function testLocalSqlSyncD6() {
    if (UNISH_DRUPAL_MAJOR_VERSION != 6) {
      $this->markTestSkipped('This test class is designed for Drupal 6.');
      return;
    }

    chdir(UNISH_TMP); // Avoids perm denied Windows error.
    $this->setUpBeforeClass();
    $sites = $this->setUpDrupal(2, TRUE, '6');
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

    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      // Add user fields and a test User.
      $this->drush('pm-enable', array('field,text,telephone,comment'), $options + array('yes' => NULL));
      $this->drush('php-script', array(
        'user_fields-D' . UNISH_DRUPAL_MAJOR_VERSION,
        $name,
        $mail
      ), $options + array(
          'script-path' => __DIR__ . '/resources',
          'debug' => NULL
        ));
    }
    else {
      $this->drush('user-create', array($name), $options + array('password' => 'password', 'mail' => $mail));
    }

    // Copy stage to dev with --sanitize.
    $sync_options = array(
      'sanitize' => NULL,
      'yes' => NULL,
      // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
      'structure-tables-list' => 'cache,cache*',
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
    $this->assertEquals("user+$uid@localhost.localdomain", $row[2], 'email address was sanitized on destination site.');
    $this->assertEquals($name, $row[0]);

    // @todo Confirm that the role_permissions table no longer exists in dev site (i.e. wildcard expansion works in sql-sync).
    // $this->drush('sql-query', array('SELECT * FROM role_permission'), $options, NULL, NULL, self::EXIT_ERROR);

    // Copy stage to dev with --sanitize and a fixed sanitized email
    $sync_options = array(
      'sanitize' => NULL,
      'yes' => NULL,
      'sanitize-email' => 'user@mysite.org',
      // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
      'structure-tables-list' => 'cache,cache*',
    );
    $this->drush('sql-sync', array('@stage', '@dev'), $sync_options);

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
    $this->assertEquals("user@mysite.org", $row[2], 'email address was sanitized (fixed email) on destination site.');
    $this->assertEquals($name, $row[0]);

    // TODO: Make this >= 8 once the --sanitize option is fixed on Drupal 9
    if (UNISH_DRUPAL_MAJOR_VERSION == 8) {
      // Assert that field_user_telephone DOES contain "5555555555".
      $this->assertUserFieldContents('field_user_telephone', '5555555555', $options, TRUE);

      $fields = [
        'field_user_email' => 'joe.user.alt@myhome.com',
        'field_user_string' => 'Private info',
        'field_user_string_long' => 'Really private info',
      ];

      // TODO: 'text' fields currently NOT being sanitized
      //  'field_user_text' => 'Super private info',
      //  'field_user_text_long' => 'Super duper private info',
      //  'field_user_text_with_summary' => 'Private',

      // Assert that field DO NOT contain values.
      foreach ($fields as $field_name => $value) {
        $this->assertUserFieldContents($field_name, $value, $options);
      }
    }
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
      $this->assertStringContainsString($value, $output);
    }
    else {
      $this->assertStringNotContainsString($value, $output);
    }
  }
}
