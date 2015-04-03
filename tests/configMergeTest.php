<?php

/**
* @file
*  Test config-merge, that merges configuration changes from one site to another.
*/

namespace Unish;

/**
 *  @group slow
 *  @group commands
 */
class configMergeTest extends CommandUnishTestCase {

  /**
   * Covers the following responsibilities.
   *   - The site name configuration property is set on the 'stage' site.
   *   - config-merge is used to merge the change into the 'dev' site.
   *   - The site name is tested to confirm that it changed.
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

    // Both sites must be based off of the same install; otherwise, the uuids
    // for the initial configuration items will not match, which will cause
    // problems.
    $this->drush('sql-sync', array('@self', 'stage'), $dev_options);

    // Export initial configuration
    $this->drush('config-export', array(), $dev_options);
    $this->drush('config-export', array(), $stage_options);

    // Make a git repository
    $this->createGitRepository($this->webroot());

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $stage_options);

    // Run config-merge to merge the configuration change from 'stage' into the 'dev' site's configuration
    $this->drush('config-merge', array('stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config set, merged and fetched.');
  }

  protected function createGitRepository($dir) {
    $this->execute("git init && git add . && git commit -m 'Initial commit.'", CommandUnishTestCase::EXIT_SUCCESS, $dir);
  }
}
