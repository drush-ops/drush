<?php

/**
  * @file
  *   Prepare a codebase and upgrade it in several stages, exercising
  *   updatecode's filters.
  *   @todo test security-only once one of these modules or core gets a security release.
  */

/**
 *  @group slow
 *  @group pm
 */
class pmUpdateCode extends Drush_CommandTestCase {

  /**
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
      'strict' => 0, // invoke from script: do not verify options
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
    $backup_dir = UNISH_SANDBOX . '/backups';
    $Directory = new RecursiveDirectoryIterator($backup_dir);
    $Iterator = new RecursiveIteratorIterator($Directory);
    $found = FALSE;
    foreach ($Iterator as $item) {
      if (basename($item) == 'devel.module') {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Backup exists and contains devel module.');



    $Iterator = new RecursiveIteratorIterator($Directory);
    $found = FALSE;
    foreach ($Iterator as $item) {
      if (basename($item) == 'webform.module') {
        $found = TRUE;
        break;
      }
    }
    $this->assertFalse($found, 'Backup exists and does not contain webformmodule.');
  }
}
