<?php

namespace Unish;

/**
 * @group base
 */
class VendorVersionTest extends UnishTestCase {

  /**
   * Tests for specific vendor versions.
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
      $this->fail('PHPDocumentor is not the right version');
    }
  }
}
