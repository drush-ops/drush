<?php

namespace Unish;

/**
 * Make makefile tests.
 * @group make
 * @group slow
 */
class makeConvertCase extends CommandUnishTestCase {

  /**
   * Tests the conversion of make file to various formats.
   *
   * @param string $source_filename
   *   The source file to be converted.
   *
   * @param $options
   *   Options to be passed to the make-convert command. E.g., --format=yml.
   *
   * @param $expected_lines
   *   An array of lines expected to be present in the command output.
   *
   * @dataProvider providerTestMakeConvert
   */
  public function testMakeConvert($source_filename, $options, $expected_lines) {
    $makefile_dir =  dirname(__FILE__) . DIRECTORY_SEPARATOR . 'makefiles';
    $source_file = $makefile_dir . DIRECTORY_SEPARATOR . $source_filename;
    $return = $this->drush('make-convert', array($source_file), $options);
    $output = $this->getOutput();
    foreach ($expected_lines as $expected_line) {
      $this->assertStringContainsString($expected_line, $output);
    }
  }

  /**
   * Data provider for testMakeConvert().
   *
   * @return array
   *   An array of test case data. See testMakeConvert() signature.
   */
  public function providerTestMakeConvert() {
    return array(
      array(
        // Source filename in makefiles directory.
        'patches.make',
        // Command pptions.
        array('format' => 'yml'),
        // Expected output lines.
        array(
          'core: 7.x',
          'features:',
          'version: 1.0-beta4',
          'patch:',
          "- 'http://drupal.org/files/issues/features-drush-backend-invoke-25.patch'",
        ),
      ),
      array(
        'patches.make',
        array('format' => 'composer'),
        array(
          '"drupal/drupal": "7.*",',
          '"drupal/features": "^1.0-beta4",',
          '"patches": {',
          '"drupal/features": {',
          '"Enter drupal/features patch #0 description here": "http://drupal.org/files/issues/features-drush-backend-invoke-25.patch"',
        ),
      ),
      array(
        'patches.make.yml',
        array('format' => 'composer'),
        array(
          '"drupal/drupal": "7.*",',
          '"drupal/features": "^1.0-beta4",',
          '"patches": {',
          '"drupal/features": {',
          '"Enter drupal/features patch #0 description here": "http://drupal.org/files/issues/features-drush-backend-invoke-25.patch"',
        ),
      ),
      array(
        'composer.lock',
        array('format' => 'make'),
        array(
          'core = 7.x',
          'api = 2',
          // Ensure Drupal core tag is set correctly.
          'projects[drupal][download][tag] = "7.67"',
          'projects[features][download][type] = "git"',
          'projects[features][download][url] = "https://git.drupalcode.org/project/features.git"',
          'projects[features][download][tag] = "1.x-0.0-beta4"',
          'projects[features][patch][0] = "https://drupal.org/files/issues/features-drush-backend-invoke-25.patch"'),
      ),
      array(
        'composer.lock',
        array('format' => 'yml'),
        array(
          'core: 7.x',
          'api: 2',
          // Ensure Drupal core tag is set correctly.
          "tag: '7.67'",
          'features:',
          'tag: 1.x-0.0-beta4',
          'patch:',
          "- 'https://drupal.org/files/issues/features-drush-backend-invoke-25.patch'",
        ),
      ),
    );
  }

}
