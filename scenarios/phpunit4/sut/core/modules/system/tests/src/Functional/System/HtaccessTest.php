<?php

namespace Drupal\Tests\system\Functional\System;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests .htaccess is working correctly.
 *
 * @group system
 */
class HtaccessTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'path'];

  /**
   * Get an array of file paths for access testing.
   *
   * @return int[]
   *   An array keyed by file paths. Each value is the expected response code,
   *   for example, 200 or 403.
   */
  protected function getProtectedFiles() {
    $path = drupal_get_path('module', 'system') . '/tests/fixtures/HtaccessTest';

    // Tests the FilesMatch directive which denies access to certain file
    // extensions.
    $file_exts_to_deny = [
      'engine',
      'inc',
      'install',
      'make',
      'module',
      'module~',
      'module.bak',
      'module.orig',
      'module.save',
      'module.swo',
      'module.swp',
      'php~',
      'php.bak',
      'php.orig',
      'php.save',
      'php.swo',
      'php.swp',
      'profile',
      'po',
      'sh',
      'sql',
      'theme',
      'twig',
      'tpl.php',
      'xtmpl',
      'yml',
    ];

    foreach ($file_exts_to_deny as $file_ext) {
      $file_paths["$path/access_test.$file_ext"] = 403;
    }

    // Tests the .htaccess file in vendor and created by a Composer script.
    // Try and access a non PHP file in the vendor directory.
    // @see Drupal\\Core\\Composer\\Composer::ensureHtaccess
    $file_paths['vendor/composer/installed.json'] = 403;

    // Tests the rewrite conditions and rule that denies access to php files.
    $file_paths['core/lib/Drupal.php'] = 403;
    $file_paths['vendor/autoload.php'] = 403;
    $file_paths['autoload.php'] = 403;

    // Test extensions that should be permitted.
    $file_exts_to_allow = [
      'php-info.txt',
    ];

    foreach ($file_exts_to_allow as $file_ext) {
      $file_paths["$path/access_test.$file_ext"] = 200;
    }

    // Ensure composer.json and composer.lock cannot be accessed.
    $file_paths["$path/composer.json"] = 403;
    $file_paths["$path/composer.lock"] = 403;

    return $file_paths;
  }

  /**
   * Iterates over protected files and calls assertNoFileAccess().
   */
  public function testFileAccess() {
    foreach ($this->getProtectedFiles() as $file => $response_code) {
      $this->assertFileAccess($file, $response_code);
    }

    // Test that adding "/1" to a .php URL does not make it accessible.
    $this->drupalGet('core/lib/Drupal.php/1');
    $this->assertResponse(403, "Access to core/lib/Drupal.php/1 is denied.");

    // Test that it is possible to have path aliases containing .php.
    $type = $this->drupalCreateContentType();

    // Create an node aliased to test.php.
    $node = $this->drupalCreateNode([
      'title' => 'This is a node',
      'type' => $type->id(),
      'path' => '/test.php',
    ]);
    $node->save();
    $this->drupalGet('test.php');
    $this->assertResponse(200);
    $this->assertText('This is a node');

    // Update node's alias to test.php/test.
    $node->path = '/test.php/test';
    $node->save();
    $this->drupalGet('test.php/test');
    $this->assertResponse(200);
    $this->assertText('This is a node');
  }

  /**
   * Asserts that a file exists and requesting it returns a specific response.
   *
   * @param string $path
   *   Path to file. Without leading slash.
   * @param int $response_code
   *   The expected response code. For example: 200, 403 or 404.
   */
  protected function assertFileAccess($path, $response_code) {
    $this->assertTrue(file_exists(\Drupal::root() . '/' . $path), "The file $path exists.");
    $this->drupalGet($path);
    $this->assertResponse($response_code, "Response code to $path is $response_code.");
  }

  /**
   * Tests that SVGZ files are served with Content-Encoding: gzip.
   */
  public function testSvgzContentEncoding() {
    $this->drupalGet('core/modules/system/tests/logo.svgz');
    $this->assertResponse(200);

    // Use x-encoded-content-encoding because of Content-Encoding responses
    // (gzip, deflate, etc.) are automatically decoded by Guzzle.
    $header = $this->drupalGetHeader('x-encoded-content-encoding');
    $this->assertEqual($header, 'gzip');
  }

}
