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
    // Setup two Drupal sites. Skip install for speed.
    $sites = $this->setUpDrupal(2);
    $uri = key($sites);
    $root = $this->webroot();

    $devel_options = array(
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'invoke' => NULL, // invoke from script: do not verify options
    );

    // Default to sites/all
    $options = array(
      'root' => $root,
      'uri' => $uri,
    ) + $devel_options;
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists($root . '/sites/all/modules/devel/README.txt');

    // If we are in site specific dir, then download belongs there.
    $path_stage = "$root/sites/$uri";
    mkdir("$path_stage/modules");
    $options = $devel_options;
    $this->drush('pm-download', array('devel'), $options, NULL, $path_stage);
    $this->assertFileExists($path_stage . '/modules/devel/README.txt');

    // --destination with absolute path.
    $destination = UNISH_SANDBOX . '/test-destination1';
    mkdir($destination);
    $options = array(
      'destination' => $destination,
    ) + $devel_options;
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists($destination . '/devel/README.txt');

    // --destination with a relative path.
    $destination = 'test-destination2';
    mkdir(UNISH_SANDBOX . '/' . $destination);
    $options = array(
      'destination' => $destination,
    ) + $devel_options;
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists(UNISH_SANDBOX . '/' . $destination . '/devel/README.txt');
  }
}
