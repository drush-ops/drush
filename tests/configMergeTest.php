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

    // Copy settings.php from sites/dev to sites/default.
    $dev_settings = file_get_contents($sites['dev']['root'] . '/sites/dev/settings.php');
    file_put_contents($sites['dev']['root'] . '/sites/default/settings.php', $dev_settings);

    $dev_options = array(
      'root' => $sites['dev']['root'],
      'uri' => 'default',
      'yes' => NULL,
    );

    $stage_options = array(
      'root' => dirname($sites['dev']['root']) . '/stage',
      'uri' => 'default',
      'yes' => NULL,
      'tool' => '0',
      'strict' => '0',
    );

    // Create a root directory for the staging site
    mkdir($stage_options['root']);

    // Create a site alias for the staging site.
    $this->writeSiteAlias('stage', $stage_options['root'], 'default');

    // Export initial configuration for dev site
    $this->drush('config-export', array(), $dev_options);

    // Write a .gitignore file for the dev site, to ignore settings.php and the files/php directory.
    file_put_contents($dev_options['root'] . '/.gitignore', "sites/default/settings.php\nsites/default/files/php");
    // Make a git repository for the dev site.
    $this->createGitRepository($dev_options['root']);

    // We have to check out the files in the 'stage' site from
    // the git repository of the 'dev' site so that we can
    // use git to transfer configuration.

    // make a bare repository, push the dev site up to it, and clone from there.
    $master_repo = dirname($dev_options['root']) . '/config-merge.git';
    mkdir($master_repo, 0777, TRUE);
    $this->execute("git init --bare", CommandUnishTestCase::EXIT_SUCCESS, $master_repo);
    $this->execute("git remote add origin file://" . $master_repo . " && git push origin master", CommandUnishTestCase::EXIT_SUCCESS, $dev_options['root']);
    $this->execute("git clone file://" . $master_repo . " " . $stage_options['root']);
    $this->execute("git config user.email 'unish@drush.org' && git config user.name 'Unish'", CommandUnishTestCase::EXIT_SUCCESS, $stage_options['root']);

    // Change the db settings in $stage_settings
    $stage_settings = str_replace("_dev", "_stage", $dev_settings);
    file_put_contents($stage_options['root'] . '/sites/default/settings.php', $stage_settings);

    // Both sites must be based off of the same install; otherwise, the uuids
    // for the initial configuration items will not match, which will cause
    // problems.
    $this->drush('sql-sync', array('@self', '@stage'), $dev_options);

    // Part one:  test config-merge using the git push / pull mechanism
    // We have to test 'git' first, because it requires both sites to stay
    // in sync with the upstream repository.  In contrast, the format-patch
    // and rsync mechanisms presume that the remote site cannot reach the
    // central repository, so they do not attempt to keep the remote side
    // in sync.  Doing those tests later means that we do not need to clean
    // up the repository.

    // Get the last commit hash
    $this->execute("git log --pretty=format:%h -1", CommandUnishTestCase::EXIT_SUCCESS, $dev_options['root']);
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
    $this->execute("git log --pretty=format:%h -1", CommandUnishTestCase::EXIT_SUCCESS, $dev_options['root']);
    $base = $this->getOutput();

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'format_patch'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options + array('format-patch' => NULL, 'base' => $base));

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': format_patch", $this->getOutput(), 'Config set, merged and fetched via format-patch.');

    // Get the last commit hash
    $this->execute("git log --pretty=format:%h -1", CommandUnishTestCase::EXIT_SUCCESS, $dev_options['root']);
    $base = $this->getOutput();

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'format_patch-2'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options + array('format-patch' => NULL, 'base' => $base));

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': format_patch-2", $this->getOutput(), 'Config set, merged and fetched via format-patch.');

    // Part three:  test config-merge using the rsync mechanism

    // Make a configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config set, merged and fetched via rsync.');

    $this->execute("git reset --hard", CommandUnishTestCase::EXIT_SUCCESS, $dev_options['root']);

    // Make a second configuration change on 'stage' site
    $this->drush('config-set', array('system.site', 'name', 'config_test_2'), $stage_options);

    // Run config-merge to copy the configuration change to the 'dev' site
    $this->drush('config-merge', array('@stage'), $dev_options);

    // Verify that the configuration change we made on 'stage' now exists on 'dev'
    $this->drush('config-get', array('system.site', 'name'), $dev_options);
    $this->assertEquals("'system.site:name': config_test_2", $this->getOutput(), 'Config set, merged and fetched via rsync.');


    // Finally, make sure that we have all of the commits we should
    // in the 'dev' site
    $this->execute("git log", CommandUnishTestCase::EXIT_SUCCESS, $dev_options['root']);
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
Merged configuration from @stage in format_patch-2
Collection Config Operation
system.site update
Merged configuration from @stage in format_patch
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

  protected function createGitRepository($dir) {
    unish_file_delete_recursive($dir . '/.git');
    $this->execute("git init && git config user.email 'unish@drush.org' && git config user.name 'Unish' && git add . && git commit -m 'Initial commit.'", CommandUnishTestCase::EXIT_SUCCESS, $dir);
  }
}
