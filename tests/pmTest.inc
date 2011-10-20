<?php

/**
  * pm testing
  */
class pmCase extends Drush_UnitTestCase {

  /*
   * Parse Drupal version and release from project specification.
   *
   * @see pm_parse_project_version().
   */
  public function testVersionString() {
    require_once DRUSH_BASE_PATH . '/commands/pm/pm.drush.inc';
    $request_data = pm_parse_project_version(array('devel-6.x-1.18'));
    $this->assertArrayHasKey('devel', $request_data);
    $this->assertEquals($request_data['devel']['drupal_version'], '6.x');
    $this->assertEquals($request_data['devel']['project_version'], '1.18');
  }

  /*
   * Pick right release from the XML (dev, latest published+recommended, ...).
   */
  public function testReleaseXML() {
    // require_once DRUSH_BASE_PATH . '/commands/pm/pm.drush.inc';
    require_once DRUSH_BASE_PATH . '/commands/pm/download.pm.inc';

    // Use a local, static XML file because live files change over time.
    $xml = simplexml_load_file(dirname(__FILE__). '/devel.xml');

    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
      'project_version' => '1.18',
      'version' => '6.x-1.18',
    );

    // Pick specific release.
    $release = updatexml_parse_release($request_data, $xml);
    $this->assertEquals($release['version'], '6.x-1.18');

    // Pick latest recommended+published with no further specification.
    // 6.x-2.2 is skipped because it is unpublished.
    // 6.x-2.2-rc1 is skipped because it is not a stable release.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
    );
    $release = updatexml_parse_release($request_data, $xml);
    $this->assertEquals($release['version'], '6.x-2.1');
  }
}