<?php

namespace Unish;

/**
 * @group base
 */
class VendorVersionTest extends UnishTestCase {

  /**
   * Tests for specific phpdocumentor/reflection-docblock versions.
   *
   * The version should be 2.x.
   */
  public function testReflectionDocBlockVersion() {
    $passed = FALSE;
    $file = file_get_contents(__DIR__  . '/../composer.lock');
    $decoded_file = json_decode($file, TRUE);
    foreach($decoded_file['packages'] as $package) {
      if($package['name'] == 'phpdocumentor/reflection-docblock') {
        if(substr($package['version'], 0, 1) == 2) {
          $passed = TRUE;
        }
      }
    }

    if($passed == FALSE) {
      $this->fail('Wrong phpdocumentor/reflection-docblock version. Version should be 2.x.');
    }
  }
}
