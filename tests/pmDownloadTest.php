<?php

namespace Unish;

/**
  * @group pm
  */
class pmDownloadCase extends CommandUnishTestCase {
  public function testPmDownload() {
    $this->drush('pm-download', array('devel'), array('cache' => NULL, 'skip' => NULL)); // No FirePHP
    $this->assertFileExists(UNISH_SANDBOX . '/devel/README.txt');

    $this->drush('pm-download', array('drupal-7.500'), array('backend' => NULL), NULL, NULL, self::EXIT_ERROR);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertArrayHasKey('DRUSH_PM_COULD_NOT_FIND_VERSION', $parsed['error_log']);
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
    unish_file_delete_recursive("{$root}/sites/{$uri}/modules/devel", TRUE);

    // If we are in site specific dir, then download belongs there.
    $path_stage = "$root/sites/$uri";
    // dir gets created by --use-site-dir above,
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
      'select' => NULL,
      'choice' => 0, // Cancel.
    );
    // --select. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), $options, NULL, NULL, CommandUnishTestCase::UNISH_EXITCODE_USER_ABORT);
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
     // 4 items are: Select message + Cancel + 2 versions.
    $this->assertEquals(4, count($items), '--select offerred 2 options.');
    $this->assertContains('6.x-1.x-dev', $output, 'Dev release was shown by --select.');

    // --select --dev. Specify 6.x since that has so many releases.
    $this->drush('pm-download', array('devel-6.x'), $options + array('dev' => NULL), NULL, NULL, CommandUnishTestCase::UNISH_EXITCODE_USER_ABORT);
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
    // 12 items are: Select message + Cancel + 1 option.
    $this->assertEquals(3, count($items), '--select --dev expected to offer only one option.');
    $this->assertContains('6.x-1.x-dev', $output, 'Assure that --dev lists the only dev release.');

    // --select --all. Specify 5.x since this is frozen.
    $this->drush('pm-download', array('devel-5.x'), $options + array('all' => NULL), NULL, NULL, CommandUnishTestCase::UNISH_EXITCODE_USER_ABORT);
    $items = $this->getOutputAsList();
    $output = $this->getOutput();
    // 12 items are: Select message + Cancel + 9 options.
    $this->assertEquals(11, count($items), '--select --all offerred 8 options.');
    $this->assertContains('5.x-0.1', $output, 'Assure that --all lists very old releases.');
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
