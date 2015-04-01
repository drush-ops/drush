<?php

/**
* @file
*  Test config merge, to copy configuration from one site to another.
*/

namespace Unish;

/**
 *  @group slow
 *  @group commands
 *  @group sql
 */
class configMergeTest extends CommandUnishTestCase {

  /**
   * Covers the following responsibilities.
   *   - A user created on the source site is copied to the destination site.
   *   - The email address of the copied user is sanitized on the destination site.
   *
   * General handling of site aliases will be in sitealiasTest.php.
   */
  public function testConfigMerge() {
    if (UNISH_DRUPAL_MAJOR_VERSION != 8) {
      $this->markTestSkipped('config-merge only works with Drupal 8.');
      return;
    }

    $sites = $this->setUpDrupal(2, TRUE);

    $stage_options = array(
      'root' => $this->webroot(),
      'uri' => 'stage',
      'yes' => NULL,
      'tool' => '0',
      'strict' => '0',
    );

    $dev_options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config was successfully set, merged and fetched.');
  }
}
