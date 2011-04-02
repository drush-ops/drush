<?php

/**
  * pm-download testing
  */  
class pmDownloadCase extends Drush_TestCase {
  public function testPmDownload() {
    $this->drush('pm-download', array('devel'));
    $this->assertFileExists(UNISH_SANDBOX . '/devel/README.txt');
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
   * Pick right release from the XML (dev, latest published+recommended, ...).
   */ 
  public function testReleaseXML() {
    // Use a local, static XML file because live files change over time.
    $xml = dirname(__FILE__). '/devel.xml';
    
    // Pick specific release.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
      'project_version' => '1.18',
      'version' => '6.x-1.18',
    );
    // Build an $eval string for use with php-eval in a subprocess.
    $eval = '$request_data = ' . var_export($request_data, TRUE) . ";\n";
    $eval .= '$release = pm_parse_release($request_data, simplexml_load_file(\'' . $xml . "'));\n";
    $eval .= 'print json_encode($release);';
    $this->drush('php-eval', array($eval));
    $release = json_decode($this->getOutput());
    $this->assertEquals($release->version, '6.x-1.18');
    
    // Pick latest recommended+published with no further specification.
    // 6.x-2.2 is skipped because it is unpublished.
    // 6.x-2.2-rc1 is skipped because it is not a stable release.
    // Remove unwanted $request_data items.
    $eval = str_replace(array("'project_version' => '1.18',\n", "'version' => '6.x-1.18',\n"), NULL, $eval);
    $this->drush('php-eval', array($eval));
    $release = json_decode($this->getOutput());
    $this->assertEquals($release->version, '6.x-2.1');
  }
  
  // @todo Test pure drush commandfile projects. They get special destination.
  public function testDestination() {
    // Setup first Drupal site. Skip install for speed.
    $this->setUpDrupal('dev', FALSE);
    $root = $this->sites['dev']['root'];

    // Default to sites/all
    $this->drush('pm-download', array('devel'), array('root' => $root));
    $this->assertFileExists($root . '/sites/all/modules/devel/README.txt');

    // If we are in site specific dir, then download belongs there.
    // Setup a second site. Skip install for speed.
    $this->setUpDrupal('stage', FALSE);
    $path_stage = "$root/sites/stage";
    mkdir("$path_stage/modules");
    $this->drush('pm-download', array('devel'), array(), NULL, $path_stage);
    $this->assertFileExists($path_stage . '/modules/devel/README.txt');
  }
}