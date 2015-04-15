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
  public function testConfigMergeMultisite() {
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

    // 'config-merge' only supports 'rsync' for mutisites, so that is all we
    // are going to test here.

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $stage_options);

    // Run config-merge to merge the configuration change from 'stage' into the 'dev' site's configuration
    $this->drush('config-merge', array('stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config set, merged and fetched.');

    // Make a second configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'second_test'), $stage_options);

    // Run config-merge again to insure that the second pass also works
    $this->drush('config-merge', array('stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': second_test", $this->getOutput(), 'Config set, merged and fetched a second time.');
  }

  public function testConfigMergeSeparateSites() {
    if (UNISH_DRUPAL_MAJOR_VERSION != 8) {
      $this->markTestSkipped('config-merge only works with Drupal 8.');
      return;
    }

    // Pass $separate_roots TRUE to install each site in a different directory
    $sites = $this->setUpSingleSiteDrupal('dev', TRUE);
    $sites = $this->setUpSingleSiteDrupal('stage', TRUE);

    $dev_options = array(
      'root' => $this->webroot('dev'),
      'uri' => 'default',
      'yes' => NULL,
    );

    $stage_options = array(
      'root' => $this->webroot('stage'),
      'uri' => 'default',
      'yes' => NULL,
      'tool' => '0',
      'strict' => '0',
    );

    // Both sites must be based off of the same install; otherwise, the uuids
    // for the initial configuration items will not match, which will cause
    // problems.
    $this->drush('sql-sync', array('@self', '@stage'), $dev_options);

    // Export initial configuration
    $this->drush('config-export', array(), $dev_options);
    $this->drush('config-export', array(), $stage_options);

    // Write a .gitignore file for each site, to ignore settings.php.
    foreach (array_keys($sites) as $site) {
      file_put_contents($this->webroot($site) . '/.gitignore', "sites/default/settings.php\nsites/default/files/php");
    }
    // Make a git repository for the dev site.
    $this->createGitRepository($this->webroot('dev'));

    // We have to check out the files in the 'stage' site from
    // the git repository of the 'dev' site so that we can
    // use git to transfer configuration.  To do this, we will
    // first save the settings.php file from the stage site,
    // then we will do the checkout, and replace settings.php.
    $stage_settings = file_get_contents($this->webroot('stage') . '/sites/default/settings.php');
    unish_file_delete_recursive($this->webroot('stage'), TRUE);
    // make a bare repository, push the dev site up to it, and clone from there.
    $master_repo = dirname($this->webroot('dev')) . '/config-merge.git';
    mkdir($master_repo, 0777, TRUE);
    $this->execute("git init --bare", CommandUnishTestCase::EXIT_SUCCESS, $master_repo);
    $this->execute("git remote add origin file://" . $master_repo . " && git push origin master", CommandUnishTestCase::EXIT_SUCCESS, $this->webroot('dev'));
    $this->execute("git clone file://" . $master_repo . " " . $this->webroot('stage'));
    $this->execute("git config user.email 'unish@drush.org' && git config user.name 'Unish'", CommandUnishTestCase::EXIT_SUCCESS, $this->webroot('stage'));
    file_put_contents($this->webroot('stage') . '/sites/default/settings.php', $stage_settings);

    // Part one:  test config-merge using the git push / pull mechanism
    // We have to test 'git' first, because it requires both sites to stay
    // in sync with the upstream repository.  In contrast, the format-patch
    // and rsync mechanisms presume that the remote site cannot reach the
    // central repository, so they do not attempt to keep the remote side
    // in sync.  Doing those tests later means that we do not need to clean
    // up the repository.

    // Get the last commit hash
    $this->execute("git log --pretty=format:%h -1", CommandUnishTestCase::EXIT_SUCCESS, $this->webroot('dev'));
    $base = $this->getOutput();

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'git'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options + array('git' => NULL, 'base' => $base));

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': git", $this->getOutput(), 'Config set, merged and fetched via git.');


    // Make a second configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'git-2'), $stage_options);

    // Run config-merge again
    $this->drush('config-merge', array('@stage'), $dev_options + array('git' => NULL, 'base' => $base));

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': git-2", $this->getOutput(), 'Config set, merged and fetched via git a second time.');


    // Part two:  test config-merge using the format-patch mechanism

    // Get the last commit hash
    $this->execute("git log --pretty=format:%h -1", CommandUnishTestCase::EXIT_SUCCESS, $this->webroot('dev'));
    $base = $this->getOutput();

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'format_patch'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options + array('format-patch' => NULL, 'base' => $base));

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': format_patch", $this->getOutput(), 'Config set, merged and fetched via format-patch.');

    // Part three:  test config-merge using the rsync mechanism

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config set, merged and fetched via rsync.');

    $this->execute("git reset --hard", CommandUnishTestCase::EXIT_SUCCESS, $this->webroot('dev'));

    // Make a second configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test_2'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test_2", $this->getOutput(), 'Config set, merged and fetched via rsync.');

    $this->execute("git reset --hard", CommandUnishTestCase::EXIT_SUCCESS, $this->webroot('dev'));



  }

  protected function createGitRepository($dir) {
    $this->execute("git init && git config user.email 'unish@drush.org' && git config user.name 'Unish' && git add . && git commit -m 'Initial commit.'", CommandUnishTestCase::EXIT_SUCCESS, $dir);
  }
}
