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
    $sites = $this->setUpDrupal(2, FALSE);
    $uri = key($sites);
    $root = $this->webroot();

    // Default to sites/all
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'invoke' => NULL, // invoke from script: do not verify options
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists($root . '/sites/all/modules/devel/README.txt');

    //  --use-site-dir
    $this->drush('pm-download', array('devel'), $options + array('use-site-dir' => NULL));
    $this->assertFileExists("$root/sites/$uri/modules/devel/README.txt");
    unish_file_delete_recursive("$root/sites/$uri/modules/devel");

    // If we are in site specific dir, then download belongs there.
    $path_stage = "$root/sites/$uri";
    // gets created by --use-site-dir above,
    // mkdir("$path_stage/modules");
    $this->drush('pm-download', array('devel'), array('cache' => NULL, 'skip' => NULL, 'invoke' => NULL), NULL, $path_stage);
    $this->assertFileExists($path_stage . '/modules/devel/README.txt');

   // --select. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), array('cache' => NULL, 'no' => NULL, 'select' => NULL));
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
    $this->assertLessThanOrEqual(7, count($items), '--select offerred no more than 3 options.');
    $this->assertContains('dev', $output, 'Dev release was shown by --select.');

    // --select --all. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), array('cache' => NULL, 'no' => NULL, 'all' => NULL, 'select' => NULL));
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
    $this->assertGreaterThanOrEqual(20, count($items), '--select --all offerred at least 16 options.');
    $this->assertContains('6.x-1.5', $output, 'Assure that --all lists very old releases.');
  }
}
