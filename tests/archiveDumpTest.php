<?php

namespace Unish;

require_once dirname(__FILE__) . '/../includes/context.inc';
require_once dirname(__FILE__) . '/../includes/filesystem.inc';

/**
 * Tests for archive-dump and archive-restore
 *
 * @group commands
 */
class archiveDumpCase extends CommandUnishTestCase {
  /**
   * archive-dump behaves slightly different when archiving a site installed
   * at sites/default so we make the test to use sites/default as the
   * installation directory instead of default sites/dev.
   */
  const uri = 'default';

  /**
   * Install a site and dump it to an archive.
   */
  private function archiveDump($no_core) {
    $profile = UNISH_DRUPAL_MAJOR_VERSION >= 7 ? 'testing' : 'default';
    $this->fetchInstallDrupal(self::uri, TRUE, NULL, $profile);
    $root = $this->webroot();
    $dump_dest = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'dump.tar.gz';
    $options = array(
      'root' => $root,
      'uri' => self::uri,
      'yes' => NULL,
      'destination' => $dump_dest,
      'overwrite' => NULL,
    );
    if ($no_core) {
      $options['no-core'] = NULL;
    }
    $this->drush('archive-dump', array(self::uri), $options);

    return $dump_dest;
  }

  /**
   * Untar an archive and return the path to the untarred folder.
   */
  private function unTar($dump_dest) {
    $untar_dest = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'untar';
    unish_file_delete_recursive($untar_dest, TRUE);
    $tar = self::get_tar_executable();
    $exec = sprintf("mkdir %s && cd %s && $tar -xzf %s", $untar_dest, $untar_dest, $dump_dest);
    $this->execute($exec);

    return $untar_dest;
  }

  /**
   * Test if tarball generated by archive-dump looks right.
   */
  public function testArchiveDump() {
    $dump_dest = $this->archiveDump(FALSE);
    $docroot = basename($this->webroot());

    // Check the dump file is a gzip file.
    $exec = sprintf('file %s', $dump_dest);
    $this->execute($exec);
    $output = $this->getOutput();
    $expected = '%sgzip compressed data%s';
    $this->assertStringMatchesFormat($expected, $output);

    // Untar the archive and make sure it looks right.
    $untar_dest = $this->unTar($dump_dest);

    if (strpos(UNISH_DB_URL, 'mysql') !== FALSE) {
      $this->assertFileExists($untar_dest . '/unish_' . self::uri);
      $this->execute(sprintf('head %s/unish_%s.sql | grep "MySQL dump"', $untar_dest, self::uri));
    }
    $this->assertFileExists($untar_dest . '/MANIFEST.ini');
    $this->assertFileExists($untar_dest . '/' . $docroot);

    return $dump_dest;
  }

  /**
   * Test archive-restore.
   *
   * Restore the archive generated in testArchiveDump() and verify that the
   * directory contents are identical.
   *
   * @depends testArchiveDump
   */
   public function testArchiveRestore($dump_dest) {
    $restore_dest = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'restore';
    $options = array(
      'yes' => NULL,
      'destination' => $restore_dest,
    );
    $this->drush('archive-restore', array($dump_dest), $options);
    $original_codebase = drush_dir_md5($this->webroot());
    $restored_codebase = drush_dir_md5($restore_dest);
    $this->assertEquals($original_codebase, $restored_codebase);
  }

  /**
   * Test if tarball generated by archive-dump with --no-core looks right.
   */
  public function testArchiveDumpNoCore() {
    $dump_dest = $this->archiveDump(TRUE);
    $untar_dest = $this->unTar($dump_dest);
    $docroot = basename($this->webroot());
    $this->assertFileExists($untar_dest . '/MANIFEST.ini');
    $this->assertFileExists($untar_dest . '/' . $docroot);
    $modules_dir = UNISH_DRUPAL_MAJOR_VERSION >= 8 ? '/core/modules' : '/modules';
    $this->assertFileNotExists($untar_dest . '/' . $docroot . $modules_dir, 'No modules directory should exist with --no-core');

    return $dump_dest;
  }

  /**
   * Test archive-restore for a site archive (--no-core).
   *
   * @depends testArchiveDumpNoCore
   */
  public function testArchiveRestoreNoCore($dump_dest) {
    $root = $this->webroot();
    $original_codebase = drush_dir_md5($root);
    unish_file_delete_recursive($root . '/sites/' . self::uri, TRUE);
    $options = array(
      'yes' => NULL,
      'destination' => $root,
    );
    $this->drush('archive-restore', array($dump_dest), $options);

    $restored_codebase = drush_dir_md5($root);
    $this->assertEquals($original_codebase, $restored_codebase);
   }
}
