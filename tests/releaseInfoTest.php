<?php

/**
  * pm testing
  *
  * @group pm
  */
class releaseInfoCase extends Drush_UnitTestCase {

  /**
   * Parse Drupal version and release from project specification.
   *
   * @see pm_parse_project_version().
   */
  public function testVersionString() {
    require_once DRUSH_BASE_PATH . '/commands/pm/pm.drush.inc';
    $request_data = pm_parse_project_version(array('devel-6.x-1.18'));
    $this->assertArrayHasKey('devel', $request_data);
    $this->assertEquals('6.x', $request_data['devel']['drupal_version']);
    $this->assertEquals('1.18', $request_data['devel']['project_version']);
  }

  /**
   * Pick right release from the XML (dev, latest published+recommended, ...).
   */
  public function testReleaseXML() {
    _drush_add_commandfiles(array(DRUSH_BASE_PATH . '/commands/pm'));
    drush_include_engine('release_info', 'updatexml');

    // Use a local, static XML file because live files change over time.
    $xml = simplexml_load_file(dirname(__FILE__). '/devel.xml');

    // Pick specific release.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
      'project_version' => '1.18',
      'version' => '6.x-1.18',
    );
    $release = updatexml_parse_release($request_data, $xml);
    $this->assertEquals('6.x-1.18', $release['version']);

    // Pick latest recommended+published with no further specification.
    // 6.x-2.2 is skipped because it is unpublished.
    // 6.x-2.2-rc1 is skipped because it is not a stable release.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
    );
    $release = updatexml_parse_release($request_data, $xml);
    $this->assertEquals('6.x-2.1', $release['version']);

    // Pick latest from a specific branch.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
      'version' => '6.x-1',
    );
    $release = updatexml_parse_release($request_data, $xml);
    $this->assertEquals('6.x-1.23', $release['version']);

    // Pick latest from a different branch.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
      'version' => '6.x-2',
    );
    $release = updatexml_parse_release($request_data, $xml);
    // 6.x-2.2 is skipped because it is unpublished.
    // 6.x-2.2-rc1 is skipped because it is not a stable release.
    $this->assertEquals('6.x-2.1', $release['version']);

    // Pick a -dev release.
    $request_data = array(
      'name' => 'devel',
      'drupal_version' => '6.x',
      'version' => '6.x-1.x',
    );
    $release = updatexml_parse_release($request_data, $xml);
    $this->assertEquals('6.x-1.x-dev', $release['version']);

    // Test $restrict_to parameter.
    $request_data['version'] = '6.x-1';
    $release = updatexml_parse_release($request_data, $xml, 'version');
    $this->assertEquals('6.x-1.23', $release['version']);
    $release = updatexml_parse_release($request_data, $xml, 'dev');
    $this->assertEquals('6.x-1.x-dev', $release['version']);
  }
}
