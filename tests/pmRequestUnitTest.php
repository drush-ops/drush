<?php

/**
 * @file
 * Tests for pm parsers.
 */

namespace Unish;

/**
 * pm testing
 *
 * @group pm
 */
class pmRequestUnitCase extends UnitUnishTestCase {

  /**
   * Tests for pm_parse_version() with semantic versioning.
   */
  public function testVersionParserCoreSemVer() {
    _drush_add_commandfiles(array(DRUSH_BASE_PATH . '/commands/pm'));

    drush_set_option('default-major', UNISH_DRUPAL_MAJOR_VERSION);

    $version = '8.0.0-beta2';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('8.0.0-beta2', $version_parts['version']);
    $this->assertEquals('8.x', $version_parts['drupal_version']);
    $this->assertEquals('8', $version_parts['version_major']);
    $this->assertEquals('0', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('beta2', $version_parts['version_extra']);
    $this->assertEquals('8.0.0-beta2', $version_parts['project_version']);

    $version = '8.0.x-dev';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('8.0.x-dev', $version_parts['version']);
    $this->assertEquals('8.x', $version_parts['drupal_version']);
    $this->assertEquals('8', $version_parts['version_major']);
    $this->assertEquals('0', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('dev', $version_parts['version_extra']);
    $this->assertEquals('8.0.x-dev', $version_parts['project_version']);

    $version = '8.0.0-beta3+252-dev';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('8.0.0-beta3+252-dev', $version_parts['version']);
    $this->assertEquals('8.x', $version_parts['drupal_version']);
    $this->assertEquals('8', $version_parts['version_major']);
    $this->assertEquals('0', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('beta3', $version_parts['version_extra']);
    $this->assertEquals('252', $version_parts['version_offset']);
    $this->assertEquals('8.0.0-beta3+252-dev', $version_parts['project_version']);

    $version = '8.1.x';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('8.1.x-dev', $version_parts['version']);
    $this->assertEquals('8.x', $version_parts['drupal_version']);
    $this->assertEquals('8', $version_parts['version_major']);
    $this->assertEquals('1', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('dev', $version_parts['version_extra']);
    $this->assertEquals('8.1.x-dev', $version_parts['project_version']);

    $version = '8.0.1';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('8.0.1', $version_parts['version']);
    $this->assertEquals('8.x', $version_parts['drupal_version']);
    $this->assertEquals('8', $version_parts['version_major']);
    $this->assertEquals('0', $version_parts['version_minor']);
    $this->assertEquals('1', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('8.0.1', $version_parts['project_version']);
  }

  /**
   * Tests for pm_parse_version() with drupal version scheme for core.
   */
  public function testVersionParserCore() {
    _drush_add_commandfiles(array(DRUSH_BASE_PATH . '/commands/pm'));

    drush_set_option('default-major', UNISH_DRUPAL_MAJOR_VERSION);

    $version = '';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('', $version_parts['version']);
    $this->assertEquals(UNISH_DRUPAL_MAJOR_VERSION . '.x', $version_parts['drupal_version']);
    $this->assertEquals(UNISH_DRUPAL_MAJOR_VERSION, $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('', $version_parts['project_version']);

    // We use version 5 in these tests to avoid false positives from
    // pm_parse_version(), in case it was picking drush's default-major.

    $version = '5';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('', $version_parts['version']);
    $this->assertEquals('5.x', $version_parts['drupal_version']);
    $this->assertEquals('', $version_parts['project_version']);
    $this->assertEquals('5', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);

    $version = '5.x';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('5.x-dev', $version_parts['version']);
    $this->assertEquals('5.x', $version_parts['drupal_version']);
    $this->assertEquals('5', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('dev', $version_parts['version_extra']);
    $this->assertEquals('5.x-dev', $version_parts['project_version']);

    $version = '5.x-dev';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('5.x-dev', $version_parts['version']);
    $this->assertEquals('5.x', $version_parts['drupal_version']);
    $this->assertEquals('5', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('dev', $version_parts['version_extra']);
    $this->assertEquals('5.x-dev', $version_parts['project_version']);

    $version = '5.0';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('5.0', $version_parts['version']);
    $this->assertEquals('5.x', $version_parts['drupal_version']);
    $this->assertEquals('5', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('5.0', $version_parts['project_version']);

    $version = '5.0-beta1';
    $version_parts = pm_parse_version($version, TRUE);
    $this->assertEquals('5.0-beta1', $version_parts['version']);
    $this->assertEquals('5.x', $version_parts['drupal_version']);
    $this->assertEquals('5', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('beta1', $version_parts['version_extra']);
    $this->assertEquals('5.0-beta1', $version_parts['project_version']);
  }

  /**
   * Tests for pm_parse_version() with project versions.
   */
  public function testVersionParserContrib() {
    _drush_add_commandfiles(array(DRUSH_BASE_PATH . '/commands/pm'));

    drush_set_option('default-major', UNISH_DRUPAL_MAJOR_VERSION);

    $version = '';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('', $version_parts['version']);
    $this->assertEquals(UNISH_DRUPAL_MAJOR_VERSION . '.x', $version_parts['drupal_version']);
    $this->assertEquals('', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('', $version_parts['project_version']);

    $version = '7';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('', $version_parts['version']);
    $this->assertEquals('7.x', $version_parts['drupal_version']);
    $this->assertEquals('', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('', $version_parts['project_version']);

    $version = '7.x';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('', $version_parts['version']);
    $this->assertEquals('7.x', $version_parts['drupal_version']);
    $this->assertEquals('', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('', $version_parts['project_version']);

    $version = '7.x-1.0-beta1';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('7.x', $version_parts['drupal_version']);
    $this->assertEquals('1', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('beta1', $version_parts['version_extra']);
    $this->assertEquals('1.0-beta1', $version_parts['project_version']);

    $version = '7.x-1.0-beta1+30-dev';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('7.x', $version_parts['drupal_version']);
    $this->assertEquals('1', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('beta1', $version_parts['version_extra']);
    $this->assertEquals('30', $version_parts['version_offset']);
    $this->assertEquals('1.0-beta1+30-dev', $version_parts['project_version']);

    $version = '7.x-1.0';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('7.x', $version_parts['drupal_version']);
    $this->assertEquals('1', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('1.0', $version_parts['project_version']);

    $version = '7.x-1.0+30-dev';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('7.x', $version_parts['drupal_version']);
    $this->assertEquals('1', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('0', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('30', $version_parts['version_offset']);
    $this->assertEquals('1.0+30-dev', $version_parts['project_version']);

    // Since we're not on a bootstrapped site, the version string
    // for the following cases is interpreted as a core version.
    // Tests on a bootstrapped site are in \pmRequestCase::testVersionParser()
    $version = '6.x';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('6.x', $version_parts['drupal_version']);
    $this->assertEquals('', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('', $version_parts['project_version']);

    $version = '6.22';
    $version_parts = pm_parse_version($version);
    $this->assertEquals('6.x', $version_parts['drupal_version']);
    $this->assertEquals('', $version_parts['version_major']);
    $this->assertEquals('', $version_parts['version_minor']);
    $this->assertEquals('', $version_parts['version_patch']);
    $this->assertEquals('', $version_parts['version_extra']);
    $this->assertEquals('', $version_parts['project_version']);
  }

  /**
   * Tests for pm_parse_request().
   */
  public function testRequestParser() {
    $request = 'devel-7.x-1.2';
    $request = pm_parse_request($request);
    $this->assertEquals('devel', $request['name']);
    $this->assertEquals('7.x-1.2', $request['version']);

    $request = 'field-conditional-state';
    $request = pm_parse_request($request);
    $this->assertEquals('field-conditional-state', $request['name']);
    $this->assertEquals('', $request['version']);

    $request = 'field-conditional-state-7.x-1.2';
    $request = pm_parse_request($request);
    $this->assertEquals('field-conditional-state', $request['name']);
    $this->assertEquals('7.x-1.2', $request['version']);
  }
}
