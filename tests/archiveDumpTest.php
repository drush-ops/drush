<?php

/*
 * @file
 *   Tests for archive.drush.inc
 */
class archiveDumpCase extends Drush_CommandTestCase {

  /*
   * Test dump and extraction.
   */
  public function testArchiveDump() {
    $this->setUpDrupal(1, TRUE);
    $site = reset($this->sites);
    $root = $this->webroot();
    $uri = key($this->sites);
    $docroot = basename($root);

    $dump_dest = "dump.tar.gz";
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'yes' => NULL,
      'destination' => $dump_dest,
    );
    $this->drush('archive-dump', array($uri), $options);
    $exec = sprintf('file %s/%s', UNISH_SANDBOX, $dump_dest);
    $this->execute($exec);
    $output = $this->getOutput();
    $expected = UNISH_SANDBOX . "/dump.tar.gz: gzip compressed data, from Unix";
    $this->assertEquals($expected, $output);

    // Untar it, make sure it looks right.
    $exec = sprintf('tar -xzf %s/%s', UNISH_SANDBOX, $dump_dest);
    $untar_dest = UNISH_SANDBOX . '/untar';
    $exec = sprintf('mkdir %s && cd %s && tar xzf %s/%s', $untar_dest, $untar_dest, UNISH_SANDBOX, $dump_dest);
    $this->execute($exec);
    $this->execute(sprintf('head %s/unish_%s.sql | grep "MySQL dump"', $untar_dest, $uri));
    $this->execute('test -f ' . $untar_dest . '/MANIFEST.ini');
    $this->execute('test -d ' . $untar_dest . '/' . $docroot);
  }
}
