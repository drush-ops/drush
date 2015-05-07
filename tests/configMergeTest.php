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

    // Create a dev site; we will copy this site to create our stage site
    $sites = $this->setUpDrupal(1, TRUE);

    $dev_options = array(
      'root' => $sites['dev']['root'],
      'uri' => 'default',
      'yes' => NULL,
    );

    // Export initial configuration for dev site
    $this->drush('config-export', array(), $sites['dev'] + array('yes' => NULL, 'strict' => 0));

    // Copy the dev state to make a stage site
    $sites = $this->setUpStagingWorkflow($sites);

    $dev_options = $sites['dev'] + array(
      'yes' => NULL,
      'strict' => 0,
    );

    $stage_options = $sites['stage'] + array(
      'yes' => NULL,
      'tool' => '0',
      'strict' => '0',
    );

    // Part one:  test config-merge using the git push / pull mechanism
    // We have to test 'git' first, because it requires both sites to stay
    // in sync with the upstream repository.  In contrast, the
    // rsync mechanism presumes that the remote site cannot reach the
    // central repository, so it does not attempt to keep the remote side
    // in sync.  Doing this tests later means that we do not need to clean
    // up the repository.

    // Get the last commit hash
    $this->execute("git log --pretty=format:%h -1", CommandUnishTestCase::EXIT_SUCCESS, $sites['dev']['root']);
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

    // Part two:  test config-merge using the rsync mechanism

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config set, merged and fetched via rsync.');

    $this->execute("git reset --hard", CommandUnishTestCase::EXIT_SUCCESS, $sites['dev']['root']);

    // Make a second configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test_2'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test_2", $this->getOutput(), 'Config set, merged and fetched via rsync.');


    // Finally, make sure that we have all of the commits we should
    // in the 'dev' site
    $this->execute("git log", CommandUnishTestCase::EXIT_SUCCESS, $sites['dev']['root']);
    // First, we remove blank lines, and lines that do not begin with a space.
    // This gets rid of all of the header lines, like "commit" and "Date:" that
    // contain variable data.
    $outputList = array_filter($this->getOutputAsList(),
      function($line) {
        if (empty($line)) {
          return FALSE;
        }
        return ($line[0] == ' ');
      });
    // Next, convert all runs of spaces into a single space, and trim.
    $outputList = array_map(
      function($line) {
        return trim(preg_replace("/  */", " ", $line));
      }, $outputList);
    // Test to see if the compressed result matches our expectations.
    $this->assertEquals("Merged configuration from @stage in config_test_2
Collection Config Operation
system.site update
Merged configuration from @stage in config_test
Collection Config Operation
system.site update
Exported configuration.
Collection Config Operation
system.site update
Exported configuration.
Collection Config Operation
system.site update
Initial commit.", implode("\n", $outputList));
  }

  function setUpStagingWorkflow($sites) {

    // Copy settings.php from sites/dev to sites/default.
    $dev_settings = file_get_contents($sites['dev']['root'] . '/sites/' . $sites['dev']['uri'] . '/settings.php');
    $sites['dev']['uri'] = 'default';
    file_put_contents($sites['dev']['root'] . '/sites/' . $sites['dev']['uri'] . '/settings.php', $dev_settings);

    // Rewrite the alias file for the dev site
    $this->writeSiteAlias('dev', $sites['dev']['root'], 'default');

    // Create a site alias for the staging site.
    $sites['stage']['root'] = $sites['dev']['root'] . '-stage';
    $sites['stage']['uri'] = 'default';
    $sites['stage']['db_url'] = str_replace('dev', 'stage', $sites['dev']['db_url']);
    $this->writeSiteAlias('stage', $sites['stage']['root'], 'default');

    // Create a root directory for the staging site
    mkdir($sites['stage']['root']);

    // Write a .gitignore file for the dev site, to ignore settings.php and the files/php directory.
    file_put_contents($sites['dev']['root'] . '/.gitignore', "sites/default/settings.php\nsites/default/files/php");
    // Make a git repository for the dev site.
    $this->createGitRepository($sites['dev']['root']);

    // We have to check out the files in the 'stage' site from
    // the git repository of the 'dev' site so that we can
    // use git to transfer configuration.

    // make a bare repository, push the dev site up to it, and clone from there.
    $central_repo = dirname($sites['dev']['root']) . '/repository.git';
    mkdir($central_repo, 0777, TRUE);
    $this->execute("git init --bare", CommandUnishTestCase::EXIT_SUCCESS, $central_repo);
    $this->execute("git remote add origin file://" . $central_repo . " && git push origin master", CommandUnishTestCase::EXIT_SUCCESS, $sites['dev']['root']);
    $this->execute("git clone file://" . $central_repo . " " . $sites['stage']['root']);
    $this->execute("git config user.email 'unish@drush.org' && git config user.name 'Unish'", CommandUnishTestCase::EXIT_SUCCESS, $sites['stage']['root']);

    // Change the db settings in $stage_settings
    $stage_settings = str_replace("_dev", "_stage", $dev_settings);
    file_put_contents($sites['stage']['root'] . '/sites/default/settings.php', $stage_settings);

    // Both sites must be based off of the same install; otherwise, the uuids
    // for the initial configuration items will not match, which will cause
    // problems.
    $this->drush('sql-sync', array('@self', '@stage'), $sites['dev'] + array('yes' => NULL, 'strict' => 0));

    return $sites;
  }

  protected function createGitRepository($dir) {
    unish_file_delete_recursive($dir . '/.git');
    $this->execute("git init && git config user.email 'unish@drush.org' && git config user.name 'Unish' && git add . && git commit -m 'Initial commit.'", CommandUnishTestCase::EXIT_SUCCESS, $dir);
  }
}
