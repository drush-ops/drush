<?php

/**
  * @file
  *   Prepare a codebase and upgrade it in several stages, exercising
  *   updatecode's filters.
  *   @todo test security-only once one of these modules or core gets a security release.
  */

class pmUpdateCode extends Drush_CommandTestCase {

  /*
   * Download old core and older contrib releases which will always need updating.
   */
  public function setUp() {
    $sites = $this->setUpDrupal(1, TRUE, '7.0-rc3');
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($sites),
      'yes' => NULL,
      'quiet' => NULL,
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'invoke' => NULL, // invoke from script: do not verify options
    );
    $this->drush('pm-download', array('devel-7.x-1.0-rc1,webform-7.x-3.4-beta1'), $options);
    $this->drush('pm-enable', array('menu', 'devel', 'webform'), $options);
  }

  function testUpdateCode() {
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($this->sites), // Have to access class property since $sites in in setUp().
      'yes' => NULL,
      'backup-dir' => UNISH_SANDBOX . '/backups',
      'self-update' => 0, // Don't check for any newer Drush release.
    );

    // Try to upgrade a specific module.
    $this->drush('pm-updatecode', array('devel'), $options + array());
    // Assure that devel was upgraded and webform was not.
    $this->drush('pm-updatecode', array(), $options + array('pipe' => NULL));
    $all = $this->getOutput();
    $this->assertNotContains('devel', $all);
    $this->assertContains('webform', $all);

    // Lock webform, and update core.
    $this->drush('pm-updatecode', array(), $options + array('lock' => 'webform'));
    $list = $this->getOutputAsList(); // For debugging.
    $this->drush('pm-updatecode', array(), $options + array('pipe' => NULL));
    $all = $this->getOutput();
    $this->assertNotContains('drupal', $all, 'Core was updated');
    $this->assertContains('webform', $all, 'Webform was skipped.');

    // Unlock webform, update, and check.
    $this->drush('pm-updatecode', array(), $options + array('unlock' => 'webform', 'no-backup' => NULL));
    $list = $this->getOutputAsList();
    $this->drush('pm-updatecode', array(), $options + array('pipe' => NULL));
    $all = $this->getOutput();
    $this->assertNotContains('webform', $all, 'Webform was updated');

    // Verify that we keep backups as instructed.
    $pattern = 'find %s -iname %s';
    $backup_dir = UNISH_SANDBOX . '/backups';
    $cmd = sprintf($pattern, self::escapeshellarg($backup_dir), escapeshellarg('devel.module'));
    $this->execute($cmd);
    $output = $this->getOutput();
    $this->assertNotEmpty($output);

    $cmd = sprintf($pattern, self::escapeshellarg($backup_dir), escapeshellarg('webform.module'));
    $this->execute($cmd);
    $output = $this->getOutput();
    $this->assertEmpty($output);
  }
}
