<?php

namespace Unish;

/**
 * Filesystem related testing.
 *
 * @group base
 */
class FilesystemCase extends CommandUnishTestCase {

  public function testSbit() {
    if ($this->is_windows()) {
      $this->markTestSkipped("s-bit test doesn't apply on Windows.");
    }
    $user_group = getenv('UNISH_USERGROUP');
    if (empty($user_group)) {
      $this->markTestSkipped("s-bit test skipped because of UNISH_USERGROUP was not set.");
    }

    $dest = UNISH_SANDBOX . '/test-filesystem-sbit';
    mkdir($dest);
    chgrp($dest, $user_group);
    chmod($dest, 02755); // rwxr-sr-x

    $this->drush('pm-download', array('devel'), array('cache' => NULL, 'skip' => NULL, 'destination' => $dest));

    $group = posix_getgrgid(filegroup($dest . '/devel/README.txt'));
    $this->assertEquals($group['name'], $user_group, 'Group is preserved.');

    $perms = fileperms($dest . '/devel') & 02000;
    $this->assertEquals($perms, 02000, 's-bit is preserved.');
  }

  public function testExecuteBits() {
    if ($this->is_windows()) {
      $this->markTestSkipped("execute bit test doesn't apply on Windows.");
    }

    $dest = UNISH_SANDBOX . '/test-filesystem-execute';
    mkdir($dest);
    $this->execute(sprintf("git clone --depth=1 https://github.com/drush-ops/drush.git %s", $dest . '/drush'));

    $perms = fileperms($dest . '/drush/drush') & 0111;
    $this->assertEquals($perms, 0111, 'Execute permission is preserved.');
  }
}

