<?php

namespace Unish;

/**
  * @group pm
  */
class pmRequestCase extends CommandUnishTestCase {

  /**
   * Tests for pm_parse_version() on a bootstrapped site.
   */
  public function testVersionParser() {
    // Setup a Drupal site. Skip install for speed.
    $sites = $this->setUpDrupal(1, FALSE);
    $uri = key($sites);
    $root = $this->webroot();

    $drupal_version = UNISH_DRUPAL_MAJOR_VERSION;

    // Common options for below commands.
    $options = array(
      'root' => $root,
      'uri' => $uri,
      'format' => 'yaml',
    );

    // Tests for core versions.
    $is_core = 1;

    $version = '';
    $expected = <<<EXPECTED
version: ''
drupal_version: ${drupal_version}.x
project_version: ''
version_major: ${drupal_version}
version_minor: ''
version_patch: ''
version_extra: ''
version_offset: ''
EXPECTED;
    $this->drush('php-eval', array("return pm_parse_version('${version}', ${is_core})"), $options);
    $this->assertEquals($expected, $this->getOutput(), 'Core version not provided. Pick version of the bootstrapped site.');

    $version = '5';
    $expected = <<<EXPECTED
version: ''
drupal_version: 5.x
project_version: ''
version_major: '5'
version_minor: ''
version_patch: ''
version_extra: ''
version_offset: ''
EXPECTED;
    $this->drush('php-eval', array("return pm_parse_version('${version}', ${is_core})"), $options);
    $this->assertEquals($expected, $this->getOutput(), 'Core version provided.');

    // Tests for non-core versions.
    $is_core = 0;

    $version = '';
    $expected = <<<EXPECTED
version: ''
drupal_version: ${drupal_version}.x
project_version: ''
version_major: ${drupal_version}
version_minor: ''
version_patch: ''
version_extra: ''
version_offset: ''
EXPECTED;
    $this->drush('php-eval', array("return pm_parse_version('${version}', ${is_core})"), $options);
    $this->assertEquals($expected, $this->getOutput(), 'Project version not provided. Pick version of the bootstrapped site.');

    $version = '1.0';
    $expected = <<<EXPECTED
version: ${drupal_version}.x-1.0
drupal_version: ${drupal_version}.x
project_version: '1.0'
version_major: '1'
version_minor: ''
version_patch: '0'
version_extra: ''
version_offset: ''
EXPECTED;
    $this->drush('php-eval', array("return pm_parse_version('${version}')"), $options);
    $this->assertEquals($expected, $this->getOutput());

    $version = '1.x';
    $expected = <<<EXPECTED
version: ${drupal_version}.x-1.x-dev
drupal_version: ${drupal_version}.x
project_version: 1.x-dev
version_major: '1'
version_minor: ''
version_patch: ''
version_extra: dev
version_offset: ''
EXPECTED;
    $this->drush('php-eval', array("return pm_parse_version('${version}')"), $options);
    $this->assertEquals($expected, $this->getOutput());
  }
}
