<?php

namespace Unish;

/**
 * Tests for site-install on a Drupal 6 installation.
 *
 * @group commands
 */
class siteInstallD6Case extends CommandUnishTestCase {

  function set_up() {
    if (UNISH_DRUPAL_MAJOR_VERSION != 6) {
      $this->markTestSkipped('This test class is designed for Drupal 6.');
      return;
    }
  }

  /**
   * Test a D6 install with extra options.
   */
  public function testExtraConfigurationOptions() {
    // Set up codebase without installing Drupal.
    $sites = $this->setUpDrupal(1, FALSE, '6');
    $root = $this->webroot();
    $site = key($sites);

    // Copy the "example" test profile into the newly created site's profiles directory
    $profile_dir = "$root/profiles/example";
    mkdir($profile_dir);
    copy(dirname(__FILE__) . '/resources/example.profile', $profile_dir . '/example.profile');

    $test_string = $this->randomString();
    // example.profile Has values 0-2 defined as allowed.
    $test_int = rand(0, 2);
    $site_name = $this->randomString();

    $this->drush('site-install', array(
        // First argument is the profile name
        'example',
        // Then the extra profile options
        "myopt1=$test_string",
        "myopt2=$test_int",
      ),
      array(
        'db-url' => $this->db_url($site),
        'yes' => NULL,
        'sites-subdir' => $site,
        'root' => $root,
        'site-name' => $site_name,
        'uri' => $site,
    ));

    $this->checkVariable('site_name', $site_name, $site);
    $this->checkVariable('myopt1', $test_string, $site);
    $this->checkVariable('myopt2', $test_int, $site);
  }

  /**
   * Check the value of a Drupal variable against an expectation using drush.
   *
   * @param $name
   *   The variable name.
   * @param $value
   *   The expected value of this variable.
   * @param $site
   *   The name of an individual multisite installation site.
   */
  private function checkVariable($name, $value, $site) {
    $options = array(
      'root' => $this->webroot(),
      'uri' => $site,
    );

    $this->drush('variable-get', array($name), $options);
    $this->assertEquals("$name: $value", $this->getOutput());
  }
}
