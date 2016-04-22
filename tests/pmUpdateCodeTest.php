<?php

/**
  * @file
  *   Prepare a codebase and upgrade it in several stages, exercising
  *   updatecode's filters.
  */

namespace Unish;

/**
 *  @group slow
 *  @group pm
 */
class pmUpdateCode extends CommandUnishTestCase {

  /*
   * An array of modules to be downloaded and enabled.
   */
  public $modules;

  private function getPreviousStable($project) {
    // Call drush pm-releases and get the output
    $this->drush('pm-releases', array($project), array('all' => NULL, 'fields' => 'Release'));
    $list = $this->getOutputAsList();
    // Line 0 is "Release"
    // Line 1 is "...-dev"
    // Line 2 is current best release
    // Line 3 is the previous release
    return trim($list[3]);
  }

  /**
   * Download old core and older contrib releases which will always need updating.
   */
  public function setUp() {
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      // Make sure that we can still update from the previous release
      // to the current release.
      $core = $this->getPreviousStable("drupal-8");
      $modules_str = 'unish-8.x-1.2,honeypot-8.x-1.19-beta14';
      $this->modules = array('block', 'unish', 'honeypot');
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
      'strict' => 0,
    );

    $this->drush('pm-download', array($modules_str), $options);
    $this->drush('pm-enable', $this->modules, $options);
  }

  function testUpdateCode() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 7) {
      $this->markTestSkipped("pm-update does not work once Drupal core reaches EOL.");
    }
    $extension = UNISH_DRUPAL_MAJOR_VERSION == 8 ? '.info.yml' : '.info';
    $first = $this->modules[1];
    $second = $this->modules[2];

    $options = array(
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'yes' => NULL,
      'backup-dir' => UNISH_SANDBOX . '/backups',
      'cache' => NULL,
      'check-updatedb' => 0,
      // Needed in order to get 'Up to date' in the return value of updatestatus. See pm_project_filter().
      'verbose' => NULL,
      'strict' => 0,
    );

    // Upgrade a specific module.
    $this->drush('pm-updatecode', array($first), $options + array());

    // Assure that first was upgraded and second was not.
    $this->drush('pm-updatestatus', array(), $options + array('format' => 'json'));
    $all = $this->getOutputFromJSON();
    $this->assertEquals($all->$first->existing_version, $all->$first->candidate_version);
    $this->assertNotEquals($all->$second->existing_version, $all->$second->candidate_version);

    // Lock second, and update core.
    $this->drush('pm-updatecode', array(), $options + array('lock' => $second));
    $list = $this->getOutputAsList(); // For debugging.
    $this->drush('pm-updatestatus', array(), $options + array('format' => 'json'));
    $all = $this->getOutputFromJSON();
    $this->assertEquals($all->drupal->existing_version, $all->drupal->candidate_version);
    $this->assertNotEquals($all->$second->existing_version, $all->$second->candidate_version);

    // Unlock second, update, and check.
    $this->drush('pm-updatecode', array(), $options + array('unlock' => $second, 'no-backup' => NULL));
    $list = $this->getOutputAsList();
    $this->drush('pm-updatestatus', array(), $options + array('format' => 'json'));
    $all = $this->getOutputFromJSON();
    $this->assertEquals($all->$second->existing_version, $all->$second->candidate_version);

    // Verify that we keep backups as instructed.
    $backup_dir = UNISH_SANDBOX . '/backups';
    $Directory = new \RecursiveDirectoryIterator($backup_dir);
    $Iterator = new \RecursiveIteratorIterator($Directory);
    $found = FALSE;
    foreach ($Iterator as $item) {
      if (basename($item) == $first . $extension) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, 'Backup exists and contains the first module.');

    $Iterator = new \RecursiveIteratorIterator($Directory);
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
