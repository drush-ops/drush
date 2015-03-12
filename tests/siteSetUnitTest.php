<?php

namespace Unish;

/**
 * @group base
 */
class siteSetUnitTest extends UnitUnishTestCase {

  function testSiteSet() {
    if ($this->is_windows()) {
      $this->markTestSkipped('Site-set not currently available on Windows.');
    }

    $tmp_path = UNISH_TMP;
    putenv("TMPDIR=$tmp_path");
    $posix_pid = posix_getppid();
    $username = drush_get_username();

    $expected_file = UNISH_TMP . '/drush-env-' . $username . '/drush-drupal-site-' . $posix_pid;
    $filename = drush_sitealias_get_envar_filename();

    $this->assertEquals($expected_file, $filename);
  }
}
