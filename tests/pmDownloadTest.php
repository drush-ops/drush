<?php

/**
  * pm-download testing
  *
  * @group pm
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

    // Common options for the invocations below.
    $devel_options = array(
      'cache' => NULL,
      'skip' => NULL, // No FirePHP
      'strict' => 0, // Invoke from script: do not verify options
    );

    // Default to Drupal sitewide directory.
    $options = array(
      'root' => $root,
      'uri' => $uri,
    ) + $devel_options;
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists($root . '/' . $this->drupalSitewideDirectory() . '/modules/devel/README.txt');

    //  --use-site-dir
    // Expand above $options.
    $options += array('use-site-dir' => NULL);
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists("$root/sites/$uri/modules/devel/README.txt");
    unish_file_delete_recursive("$root/sites/$uri/modules/devel");

    // If we are in site specific dir, then download belongs there.
    $path_stage = "$root/sites/$uri";
    // gets created by --use-site-dir above,
    // mkdir("$path_stage/modules");
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

  public function testSelect() {
    $options = array(
      'cache' => NULL,
      'no' => NULL,
      'select' => NULL,
    );
    // --select. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), $options);
    $items = $this->getOutputAsList();
    $output = $this->getOutput();

    // The maximums below are higher then they usually appear since --verbose can add one.
    $this->assertLessThanOrEqual(8, count($items), '--select offerred no more than 3 options.');
    $this->assertContains('dev', $output, 'Dev release was shown by --select.');

    // --select --all. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), $options + array('all' => NULL));
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
    $this->assertGreaterThanOrEqual(20, count($items), '--select --all offerred at least 16 options.');
    $this->assertContains('6.x-1.5', $output, 'Assure that --all lists very old releases.');

    // --select --dev. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), $options + array('dev' => NULL));
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
    $this->assertLessThanOrEqual(6, count($items), '--select --dev expected to offer only one option.');
    $this->assertContains('6.x-1.x-dev', $output, 'Assure that --dev lists the only dev release.');
  }

  public function testPackageHandler() {
    $options = array(
      'cache' => NULL,
      'package-handler' => 'git_drupalorg',
      'yes' => NULL,
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->assertFileExists(UNISH_SANDBOX . '/devel/README.txt');
    $this->assertFileExists(UNISH_SANDBOX . '/devel/.git');
  }
}
