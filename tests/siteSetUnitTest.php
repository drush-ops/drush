<?php

class siteSetUnitTest extends Drush_UnitTestCase {

  function testSiteSet() {
    $tmp_path = UNISH_TMP;
    putenv("TMPDIR=$tmp_path");
    $posix_pid = posix_getppid();

    $expected_file = UNISH_TMP . '/drush-drupal-site-' . $posix_pid;
    $filename = drush_sitealias_get_envar_filename();

    $this->assertEquals($expected_file, $filename);
  }
}

