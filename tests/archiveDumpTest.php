<?php

/*
 * @file
 *   Tests for archive-dump
 * @group commands
 */
class archiveDumpCase extends Drush_CommandTestCase {

  /*
   * Test dump and extraction.
   *
   * archive-dump behaves slightly different when archiving a site installed at sites/default
   * so we make the test to use sites/default as the installation directory.
   */
  public function testArchiveDump() {
    $uri = 'default';
    $this->fetchInstallDrupal($uri, TRUE, '7.15', 'testing');
    $root = $this->webroot();
    $docroot = basename($root);

    $dump_dest = "dump.tar.gz";
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'yes' => NULL,
      'destination' => $dump_dest,
    );
    $this->drush('archive-dump', array($uri), $options);
    $exec = sprintf('file %s%s%s', UNISH_SANDBOX, DIRECTORY_SEPARATOR, $dump_dest);
    $this->execute($exec);
    $output = $this->getOutput();
    $sep = self::is_windows() ? ';' : ':';
    $expected = UNISH_SANDBOX . DIRECTORY_SEPARATOR . "dump.tar.gz$sep gzip compressed data, from";

    $this->assertStringStartsWith($expected, $output);

    // Untar it, make sure it looks right.
    $untar_dest = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'untar';
    $tar = self::get_tar_executable();
    $exec = sprintf("mkdir %s && cd %s && $tar -xzf %s%s%s", $untar_dest, $untar_dest, UNISH_SANDBOX, DIRECTORY_SEPARATOR, $dump_dest);
    $this->execute($exec);
    if (strpos(UNISH_DB_URL, 'mysql') !== FALSE) {
      $this->execute(sprintf('head %s/unish_%s.sql | grep "MySQL dump"', $untar_dest, $uri));
    }
    $this->assertFileExists($untar_dest . '/MANIFEST.ini');
    $this->assertFileExists($untar_dest . '/' . $docroot);

    // Restore archive and verify that the file structure is identical.
    require_once dirname(__FILE__) . '/../includes/filesystem.inc';
    $restore_dest = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'restore';
    $options = array(
      'yes' => NULL,
      'destination' => $restore_dest,
    );
    $this->drush('archive-restore', array(UNISH_SANDBOX . DIRECTORY_SEPARATOR . $dump_dest), $options);
    $original_codebase = drush_dir_md5($root);
    $restored_codebase = drush_dir_md5($restore_dest);
    $this->assertEquals($original_codebase, $restored_codebase);
  }
}
