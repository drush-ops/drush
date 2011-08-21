<?php

/*
 * @file
 *   Tests for core commands.
 */
class coreCase extends Drush_CommandTestCase {

  /*
   * Test standalone php-script scripts. Assure that script args and options work.
   */
  public function testStandaloneScript() {
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
}
