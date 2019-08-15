<?php

namespace Unish;

/**
 * Tests for core commands.
 *
 * @group commands
 */
class coreCase extends CommandUnishTestCase {

  function setUp() {
    if (!$this->getSites()) {
      $this->setUpDrupal(1, TRUE);
    }
  }

  /**
   * Test to see if rsync @site:%files calculates the %files path correctly.
   * This tests the non-optimized code path in drush_sitealias_resolve_path_references.
   */
  function testRsyncPercentFiles() {
    $root = $this->webroot();
    $site = key($this->getSites());
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'simulate' => NULL,
      'include-conf' => NULL,
      'include-vcs' => NULL,
      'yes' => NULL,
    );
    $this->drush('core-rsync', array("@$site:%files", "/tmp"), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1;');
    $output = $this->getOutput();
    $level = $this->log_level();
    $pattern = in_array($level, array('verbose', 'debug')) ? "Calling system(rsync -e 'ssh ' -akzv --stats --progress --yes %s /tmp);" : "Calling system(rsync -e 'ssh ' -akz --yes %s /tmp);";
    $expected = sprintf($pattern, UNISH_SANDBOX . "/web/sites/$site/files");
    $this->assertEquals($expected, $output);
  }

  /**
   * Test to see if the optimized code path in drush_sitealias_resolve_path_references
   * that avoids a call to backend invoke when evaluating %files works.
   */
  function testPercentFilesOptimization() {
    $root = $this->webroot();
    $site = key($this->getSites());
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'simulate' => NULL,
      'include-conf' => NULL,
      'include-vcs' => NULL,
      'yes' => NULL,
      'strict' => 0, // invoke from script: do not verify options
    );
    $php = '$a=drush_sitealias_get_record("@' . $site . '"); drush_sitealias_resolve_path_references($a, "%files"); print_r($a["path-aliases"]["%files"]);';
    $this->drush('ev', array($php), $options);
    $output = $this->getOutput();
    $expected = "sites/dev/files";
    $this->assertEquals($expected, $output);
  }

  /**
   * Test standalone php-script scripts. Assure that script args and options work.
   */
  public function testStandaloneScript() {
    if ($this->is_windows()) {
      $this->markTestSkipped('Standalone scripts not currently available on Windows.');
    }

    $this->drush('version', array('drush_version'), array('pipe' => NULL));
    $standard = $this->getOutput();

    // Write out a hellounish.script into the sandbox. The correct /path/to/drush
    // is in the shebang line.
    $filename = 'hellounish.script';
    $data = '#!/usr/bin/env [PATH-TO-DRUSH]

$arg = drush_shift();
drush_invoke("version", $arg);
';
    $data = str_replace('[PATH-TO-DRUSH]', UNISH_DRUSH, $data);
    $script = UNISH_SANDBOX . '/' . $filename;
    file_put_contents($script, $data);
    chmod($script, 0755);
    $this->execute("$script drush_version --pipe");
    $standalone = $this->getOutput();
    $this->assertEquals($standard, $standalone);
  }

  function testDrupalDirectory() {
    if (explode('.', UNISH_DRUPAL_MINOR_VERSION)[0] < '5') {
      $this->markTestSkipped('Test uses devel, which requires Drupal 8.5.x or later');
    }
    $root = $this->webroot();
    $sitewide = $this->drupalSitewideDirectory();
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'yes' => NULL,
      'skip' => NULL,
      'cache' => NULL,
      'strict' => 0, // invoke from script: do not verify options
    );
    $this->drush('drupal-directory', array('%files'), $options);
    $output = $this->getOutput();
    $this->assertEquals($root . '/sites/dev/files', $output);

    $this->drush('drupal-directory', array('%modules'), $options);
    $output = $this->getOutput();
    $this->assertEquals($root . $sitewide . '/modules', $output);

    $this->drush('pm-download', array('devel'), $options);
    $this->drush('pm-enable', array('devel'), $options);
    $this->drush('pm-download', array('empty_theme'), $options);

    $this->drush('drupal-directory', array('devel'), $options);
    $output = $this->getOutput();
    $this->assertEquals(realpath($root  . $sitewide . '/modules/devel'), $output);

    $this->drush('drupal-directory', array('empty_theme'), $options);
    $output = $this->getOutput();
    $this->assertEquals(realpath($root  . $sitewide . '/themes/empty_theme'), $output);
  }

  function testCoreRequirements() {
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'pipe' => NULL,
      'ignore' => 'cron,http requests,update,update_core,trusted_host_patterns', // no network access when running in tests, so ignore these
      'strict' => 0, // invoke from script: do not verify options
    );
    // Drupal 6 has reached EOL, so we will always get errors for 'update_contrib';
    // therefore, we ignore it for this release.
    if (UNISH_DRUPAL_MAJOR_VERSION < 7) {
      $options['ignore'] .= ',update_contrib';
    }
    // Verify that there are no severity 2 items in the status report
    $this->drush('core-requirements', array(), $options + array('severity' => '2'));
    $output = $this->getOutput();
    $this->assertEquals('', $output);

    $this->drush('core-requirements', array(), $options);
    $loaded = $this->getOutputFromJSON();
    // Pick a subset that are valid for D6/D7/D8.
    $expected = array(
      // 'install_profile' => -1,
      // 'node_access' => -1,
      'php' => -1,
      // 'php_extensions' => -1,
      'php_memory_limit' => -1,
      'php_register_globals' => -1,
      'settings.php' => -1,
    );
    foreach ($expected as $key => $value) {
      if (isset($loaded->$key)) {
        $this->assertEquals($value, $loaded->$key->sid);
      }
    }
  }
}
