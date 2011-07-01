<?php

/**
  * pm-download testing
  */
class pmDownloadCase extends Drush_CommandTestCase {
  public function testPmDownload() {
    $this->drush('pm-download', array('devel'), array('cache' => NULL, 'skip' => NULL)); // No FirePHP
    $this->assertFileExists(UNISH_SANDBOX . '/devel/README.txt');
  }

  // @todo Test pure drush commandfile projects. They get special destination.
  public function testDestination() {
    // Setup first Drupal site. Skip install for speed.
    $this->setUpDrupal('dev', FALSE);
    $root = $this->sites['dev']['root'];

    // Default to sites/all
    $options = array(
      'root' => $root,
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists($root . '/sites/all/modules/devel/README.txt');

    // If we are in site specific dir, then download belongs there.
    // Setup a second site. Skip install for speed.
    $this->setUpDrupal('stage', FALSE);
    $path_stage = "$root/sites/stage";
    mkdir("$path_stage/modules");
    $this->drush('pm-download', array('devel'), array('cache' => NULL, 'skip' => NULL), NULL, $path_stage);
    $this->assertFileExists($path_stage . '/modules/devel/README.txt');
  }
}
