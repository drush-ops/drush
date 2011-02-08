<?php

/**
  * pm-updatecode testing
  */
class pmUpdateCode extends Drush_TestCase {

  /*
   * Prepare a codebase and upgrade it in several stages, exercising updatecode's filters.
   * @todo test security-only once one of these modules or core gets a security release.
   */
  public function setUp() {
    $this->setUpDrupal('dev', TRUE, '7.0-rc3');
    $options = array(
      'root' => $this->sites['dev']['root'],
      'uri' => 'dev',
      'yes' => NULL,
      'quiet' => NULL,
    );
    $this->drush('pm-download', array('devel-7.x-1.0-rc1'), $options);
    $this->drush('pm-download', array('webform-7.x-3.4-beta1'), $options);
    $this->drush('pm-enable', array('devel', 'webform'), $options);

    unset($options['quiet'], $options['yes']);
    $this->drush('pm-updatecode', array(), $options + array('pipe' => NULL));
    $list = $this->getOutputAsList();
    // array_filter($list);
  }

  function testUpdateCode() {
    $options = array(
      'root' => $this->sites['dev']['root'],
      'uri' => 'dev',
      'yes' => NULL,
      'backup-dir' => UNISH_SANDBOX . '/backups',
    );

    // Try to upgrade a specific module to a specific version
    $this->drush('pm-updatecode', array('devel-7.x-1.0'), $options + array());
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
    $pattern = 'cd %s; find -iname %s';
    $backup_dir = UNISH_DRUSH . '/backups';
    $cmd = sprintf($pattern, escapeshellarg($backup_dir), 'devel.module');
    $this->execute($cmd);
    $output = $this->getOutput();

    $cmd = sprintf($pattern, escapeshellarg($backup_dir), 'webform.module');
    $this->execute($cmd);
    $output = $this->getOutput();

  }


}