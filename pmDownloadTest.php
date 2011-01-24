<?php

/**
  * pm-download testing without any Drupal.
  */  
class pmDownload_TestCase extends Drush_TestCase {
  public function testPmDownload() {
    $destination = UNISH_SANDBOX;
    $this->drush('pm-download', array('devel'), array('destination' => $destination));
    $this->assertFileExists($destination . '/devel/README.txt');
  }

  /*
   * Parse Drupal version and release from command argument.
   *
   * --dev option bypasses the logic tested here.
   * 
   * @see pm_parse_project_version().
   */ 
  public function testVersionString() {
    $eval = 'print json_encode(pm_parse_project_version(array("devel-6.x-1.18")));';
    $this->drush('php-eval', array($eval));
    $request_data = json_decode($this->getOutput());
    $this->assertObjectHasAttribute('devel', $request_data);
    $this->assertEquals($request_data->devel->drupal_version, '6.x');
    $this->assertEquals($request_data->devel->project_version, '1.18');
  }

  /*
   * Pick right release from the XML (dev, latest recommended, ...).
   */ 
  public function testReleaseXML() {
    
  }
}

/**
  * pm-download testing with Drupal.
  */
class pmDownload_DrupalTestCase extends Drush_DrupalTestCase {
  public function testDestination() {
    $root = $this->sites['dev']['root'];

    // Default to sites/all
    $this->drush('pm-download', array('devel'), array('root' => $root));
    $this->assertFileExists($root . '/sites/all/modules/devel/README.txt');

    // If we are in site specific dir, then download belongs there.
    $this->setUpDrupal('stage'); // Install a second site.
    $orig = getcwd();
    $path_stage = "$root/sites/stage";
    mkdir($path_stage . '/modules');
    // Perhaps enhance $this->drush() so it can internally chdir() if directed.
    chdir($path_stage);
    $this->drush('pm-download', array('devel'), array('root' => $root));
    $this->assertFileExists($path_stage . '/modules/devel/README.txt');
    chdir($orig);

    // @todo Pure drush commandfiles are special.
  }
}