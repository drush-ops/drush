<?php

/*
 * @file
 *   Tests for core commands.
 *
 * @group commands
 */
class coreCase extends Drush_CommandTestCase {
  /**
   * Test to see if rsync @site:%files calculates the %files path correctly.
   * This tests the non-optimized code path in drush_sitealias_resolve_path_references.
   */
  function testRsyncPercentFiles() {
    $this->setUpDrupal(1, TRUE);
    $root = $this->webroot();
    $site = key($this->sites);
    $options = array(
      'root' => $root,
      'uri' => key($this->sites),
      'simulate' => NULL,
      'include-conf' => NULL,
      'include-vcs' => NULL,
      'yes' => NULL,
      'invoke' => NULL, // invoke from script: do not verify options
    );
    $this->drush('core-rsync', array("@$site:%files", "/tmp"), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1;');
    $output = $this->getOutput();
    $level = $this->log_level();
    $pattern = in_array($level, array('verbose', 'debug')) ? "Calling system(rsync -e 'ssh ' -akzv --stats --progress --yes --invoke %s /tmp);" : "Calling system(rsync -e 'ssh ' -akz --yes --invoke %s /tmp);";
    $expected = sprintf($pattern, UNISH_SANDBOX . "/web/sites/$site/files");
    $this->assertEquals($expected, $output);
  }

  /**
   * Test to see if the optimized code path in drush_sitealias_resolve_path_references
   * that avoids a call to backend invoke when evaluating %files works.
   */
  function testPercentFilesOptimization() {
    $this->setUpDrupal(1, TRUE);
    $root = $this->webroot();
    $site = key($this->sites);
    $options = array(
      'root' => $root,
      'uri' => key($this->sites),
      'simulate' => NULL,
      'include-conf' => NULL,
      'include-vcs' => NULL,
      'yes' => NULL,
      'invoke' => NULL, // invoke from script: do not verify options
    );
    $php = '$a=drush_sitealias_get_record("@' . $site . '"); drush_sitealias_resolve_path_references($a, "%files"); print_r($a["path-aliases"]["%files"]);';
    $this->drush('ev', array($php), $options);
    $output = $this->getOutput();
    $expected = "sites/dev/files";
    $this->assertEquals($expected, $output);
  }

  /*
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
    $this->setUpDrupal(1, TRUE);
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => key($this->sites),
      'verbose' => NULL,
      'skip' => NULL, // No FirePHP
      'yes' => NULL,
      'cache' => NULL,
      'invoke' => NULL, // invoke from script: do not verify options
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->drush('pm-enable', array('devel', 'menu'), $options);

    $this->drush('drupal-directory', array('devel'), $options);
    $output = $this->getOutput();
    $this->assertEquals($root . '/sites/all/modules/devel', $output);

    $this->drush('drupal-directory', array('%files'), $options);
    $output = $this->getOutput();
    $this->assertEquals($root . '/sites/dev/files', $output);

    $this->drush('drupal-directory', array('%modules'), $options);
    $output = $this->getOutput();
    $this->assertEquals($root . '/sites/all/modules', $output);
  }

  function testCoreRequirements() {
    $this->setUpDrupal(1, TRUE);
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => key($this->sites),
      'pipe' => NULL,
      'ignore' => 'cron,http requests,update_core', // no network access when running in tests, so ignore these
      'invoke' => NULL, // invoke from script: do not verify options
    );
    // Verify that there are no severity 2 items in the status report
    $this->drush('core-requirements', array(), $options + array('severity' => '2'));
    $output = $this->getOutput();
    $this->assertEquals('', $output);
    $this->drush('core-requirements', array(), $options);
    $output = $this->getOutput();
    $expected="database_system: -1
database_system_version: -1
drupal: -1
file system: -1
install_profile: -1
node_access: -1
php: -1
php_extensions: -1
php_memory_limit: -1
php_register_globals: -1
settings.php: -1
unicode: 0
update: 0
update access: -1
update status: -1
webserver: -1";
    $this->assertEquals($expected, trim($output));
  }
}
