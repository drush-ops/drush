<?php

/**
 * @file
 * Tests for release_info engine.
 */

namespace Unish;

/**
 * pm testing
 *
 * @group pm
 */
class releaseInfoCase extends UnitUnishTestCase {

  /**
   * Pick right release from the XML (dev, latest published+recommended, ...).
   */
  public function testReleaseXML() {
    _drush_add_commandfiles(array(DRUSH_BASE_PATH . '/commands/pm'));
    $release_info = drush_include_engine('release_info', 'updatexml');

    // Use a local, static XML file because live files change over time.
    $xml = simplexml_load_file(dirname(__FILE__). '/devel.xml');
    $project_release_info = new \Drush\UpdateService\Project($xml);

    // Pick specific release.
    $release = $project_release_info->getSpecificRelease('6.x-1.18');
    $this->assertEquals('6.x-1.18', $release['version']);

    // Pick latest recommended+published with no further specification.
    // 6.x-2.2 is skipped because it is unpublished.
    // 6.x-2.2-rc1 is skipped because it is not a stable release.
    $release = $project_release_info->getRecommendedOrSupportedRelease();
    $this->assertEquals('6.x-2.1', $release['version']);

    // Pick latest from a specific branch.
    $release = $project_release_info->getSpecificRelease('6.x-1');
    $this->assertEquals('6.x-1.23', $release['version']);

    // Pick latest from a different branch.
    // 6.x-2.2 is skipped because it is unpublished.
    // 6.x-2.2-rc1 is skipped because it is not a stable release.
    $release = $project_release_info->getSpecificRelease('6.x-2');
    $this->assertEquals('6.x-2.1', $release['version']);

    // Pick a -dev release.
    $release = $project_release_info->getSpecificRelease('6.x-1.x');
    $this->assertEquals('6.x-1.x-dev', $release['version']);

    // Test UpdateServiceProject::getSpecificRelease().
    // Test we get latest release in branch 1.
    $release = $project_release_info->getSpecificRelease('6.x-1');
    $this->assertEquals('6.x-1.23', $release['version']);

    // Test UpdateServiceProject::getDevRelease().
    $release = $project_release_info->getDevRelease();
    $this->assertEquals('6.x-1.x-dev', $release['version']);
  }
}
