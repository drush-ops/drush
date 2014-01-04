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

  /*
   * An array of modules to be downloaded and enabled.
   */
  public $modules;

  /**
   * Download old core and older contrib releases which will always need updating.
   */
  public function setUp() {
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $core = '8.0-alpha6';
      $modules_str = 'instagram_block-8.x-1.0,honeypot-8.x-1.14-beta5';
      $this->modules = array('block', 'instagram_block', 'honeypot');
    }
    elseif (UNISH_DRUPAL_MAJOR_VERSION == 7) {
      $core = '7.0-rc3';
      $modules_str = 'devel-7.x-1.0-rc1,webform-7.x-3.4-beta1';
      $this->modules = array('menu', 'devel', 'webform');
    }
    else {
      $core = '6.28';
      $modules_str = 'devel-6.x-1.26,webform-6.x-3.18';
      $this->modules = array('menu', 'devel', 'webform');
    }

    $sites = $this->setUpDrupal(1, TRUE, $core);
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($sites),
      'yes' => NULL,
      'quiet' => NULL,
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'strict' => 0, // invoke from script: do not verify options
    );

    $this->drush('pm-download', array($modules_str), $options);
    $this->drush('pm-enable', $this->modules, $options);
  }

  function testUpdateCode() {
    $first = $this->modules[1];
    $second = $this->modules[2];

    $options = array(
      'root' => $this->webroot(),
      'uri' => key($this->sites), // Have to access class property since $sites in in setUp().
      'yes' => NULL,
      'backup-dir' => UNISH_SANDBOX . '/backups',
      'cache' => NULL,
      'check-updatedb' => 0,
      'strict' => 0,
    );

    // Try to upgrade a specific module.
    $this->drush('pm-updatecode', array($first), $options + array());
    // Assure that first was upgraded and second was not.
    $this->drush('pm-updatestatus', array(), $options + array());
    $all = $this->getOutput();
    $this->assertNotContains($first, $all);
    $this->assertContains($second, $all);

    // Lock second, and update core.
    $this->drush('pm-updatecode', array(), $options + array('lock' => $second));
    $list = $this->getOutputAsList(); // For debugging.
    $this->drush('pm-updatestatus', array(), $options + array());
    $all = $this->getOutput();
    $this->assertNotContains('drupal', $all, 'Core was updated');
    $this->assertContains($second, $all, 'Second was skipped.');

    // Unlock second, update, and check.
    $this->drush('pm-updatecode', array(), $options + array('unlock' => $second, 'no-backup' => NULL));
    $list = $this->getOutputAsList();
    $this->drush('pm-updatestatus', array(), $options + array());
    $all = $this->getOutput();
    $this->assertNotContains($second, $all, 'Second was updated');

    // Verify that we keep backups as instructed.
    $backup_dir = UNISH_SANDBOX . '/backups';
    $Directory = new RecursiveDirectoryIterator($backup_dir);
    $Iterator = new RecursiveIteratorIterator($Directory);
    $found = FALSE;
    foreach ($Iterator as $item) {
      if (basename($item) == $first . '.module') {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Backup exists and contains the first module.');

    $Iterator = new RecursiveIteratorIterator($Directory);
    $found = FALSE;
    foreach ($Iterator as $item) {
      if (basename($item) == $second . '.module') {
        $found = TRUE;
        break;
      }
    }
    $this->assertFalse($found, 'Backup exists and does not contain the second module.');
  }
}
