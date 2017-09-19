<?php

namespace Unish;

use Webmozart\PathUtil\Path;

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
  function testRsyncAndPercentFiles() {
    $this->markTestSkipped('rsync path aliases (e.g. %files) not implemented yet.');
    $root = $this->webroot();
    $site = key($this->getSites());
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'simulate' => NULL,
      'yes' => NULL,
    );
    $this->drush('core-rsync', array("@$site:%files", "/tmp"), $options, NULL, NULL, self::EXIT_SUCCESS, '2>&1;');
    $output = $this->getOutput();
    $level = $this->log_level();
    $pattern = in_array($level, array('verbose', 'debug')) ? "Calling system(rsync -e 'ssh ' -akzv --stats --progress %s /tmp);" : "Calling system(rsync -e 'ssh ' -akz %s /tmp);";
    $expected = sprintf($pattern, $this->webroot(). "/sites/$site/files");
    $this->assertEquals($expected, $output);
  }

  /**
   * Test to see if the optimized code path in drush_sitealias_resolve_path_references
   * that avoids a call to backend invoke when evaluating %files works.
   */
  function testPercentFilesOptimization() {
    $this->markTestSkipped('rsync path aliases (e.g. %files) not implemented yet.');
    $root = $this->webroot();
    $site = key($this->getSites());
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'simulate' => NULL,
      'yes' => NULL,
      // 'strict' => 0, // invoke from script: do not verify options
    );
    $php = '$a=drush_sitealias_get_record("@' . $site . '"); drush_sitealias_resolve_path_references($a, "%files"); print_r($a["path-aliases"]["%files"]);';
    $this->drush('ev', array($php), $options);
    $output = $this->getOutput();
    $expected = "sites/dev/files";
    $this->assertEquals($expected, $output);
  }

  /**
   * Test standalone php-script scripts. Assure that script args and options work.
   *
   * @requires extension WIP
   */
  public function testStandaloneScript() {
    $this->markTestSkipped('Standalone scripts not implemented yet.');
    if ($this->is_windows()) {
      $this->markTestSkipped('Standalone scripts not currently available on Windows.');
    }

    $this->drush('version', array(), array('field' => 'drush-version'));
    $standard = $this->getOutput();

    // Write out a hellounish.script into the sandbox. The correct /path/to/drush
    // is in the shebang line.
    $filename = 'hellounish.script';
    $data = '#!/usr/bin/env [PATH-TO-DRUSH]

$arg = drush_shift();
drush_invoke("version", $arg);
';
    $data = str_replace('[PATH-TO-DRUSH]', self::getDrush(), $data);
    $script = self::getSandbox() . '/' . $filename;
    file_put_contents($script, $data);
    chmod($script, 0755);
    $this->execute("$script drush_version --pipe");
    $standalone = $this->getOutput();
    $this->assertEquals($standard, $standalone);
  }

  function testDrupalDirectory() {
    $this->markTestSkipped('Depends on backend');
    $root = $this->webroot();
    $sitewide = $this->drupalSitewideDirectory();
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'yes' => NULL,
      // 'strict' => 0, // invoke from script: do not verify options
    );
    $this->drush('drupal-directory', array('%files'), $options);
    $output = $this->getOutput();
    $this->assertEquals(Path::join($root, '/sites/dev/files'), $output);

    $this->drush('drupal-directory', array('%modules'), $options);
    $output = $this->getOutput();
    $this->assertEquals(Path::join($root, $sitewide . '/modules'), $output);

    $this->drush('pm-enable', array('devel'), $options);
    $this->drush('theme-enable', array('empty_theme'), $options);

    $this->drush('drupal-directory', array('devel'), $options);
    $output = $this->getOutput();
    $this->assertEquals(Path::join($root, '/modules/unish/devel'), $output);

    $this->drush('drupal-directory', array('empty_theme'), $options);
    $output = $this->getOutput();
    $this->assertEquals(Path::join($root, '/themes/unish/empty_theme'), $output);
  }

  function testCoreRequirements() {
    $this->markTestSkipped('Core requirements not implemented yet.');
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => key($this->getSites()),
      'pipe' => NULL,
      'ignore' => 'cron,http requests,update,update_core,trusted_host_patterns', // no network access when running in tests, so ignore these
      // 'strict' => 0, // invoke from script: do not verify options
    );
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
