<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Asset\LibraryDiscoveryParserTest.
 */

namespace Drupal\Tests\Core\Asset;

use Drupal\Core\Asset\Exception\IncompleteLibraryDefinitionException;
use Drupal\Core\Asset\Exception\InvalidLibraryFileException;
use Drupal\Core\Asset\Exception\LibraryDefinitionMissingLicenseException;
use Drupal\Core\Asset\LibraryDiscoveryParser;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Asset\LibraryDiscoveryParser
 * @group Asset
 */
class LibraryDiscoveryParserTest extends UnitTestCase {

  /**
   * The tested library discovery parser service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryParser|\Drupal\Tests\Core\Asset\TestLibraryDiscoveryParser
   */
  protected $libraryDiscoveryParser;

  /**
   * The mocked cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cache;

  /**
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $themeManager;

  /**
   * The mocked lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $lock;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->moduleHandler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->themeManager = $this->getMock('Drupal\Core\Theme\ThemeManagerInterface');
    $mock_active_theme = $this->getMockBuilder('Drupal\Core\Theme\ActiveTheme')
      ->disableOriginalConstructor()
      ->getMock();
    $mock_active_theme->expects($this->any())
      ->method('getLibrariesOverride')
      ->willReturn([]);
    $this->themeManager->expects($this->any())
      ->method('getActiveTheme')
      ->willReturn($mock_active_theme);
    $this->libraryDiscoveryParser = new TestLibraryDiscoveryParser($this->root, $this->moduleHandler, $this->themeManager);
  }

  /**
   * Tests that basic functionality works for getLibraryByName.
   *
   * @covers ::buildByExtension
   */
  public function testBuildByExtensionSimple() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('example_module')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'example_module', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('example_module');
    $library = $libraries['example'];

    $this->assertCount(0, $library['js']);
    $this->assertCount(1, $library['css']);
    $this->assertCount(0, $library['dependencies']);
    $this->assertEquals($path . '/css/example.css', $library['css'][0]['data']);

    // Ensures that VERSION is replaced by the current core version.
    $this->assertEquals(\Drupal::VERSION, $library['version']);
  }

  /**
   * Tests that a theme can be used instead of a module.
   *
   * @covers ::buildByExtension
   */
  public function testBuildByExtensionWithTheme() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('example_theme')
      ->will($this->returnValue(FALSE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('theme', 'example_theme', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('example_theme');
    $library = $libraries['example'];

    $this->assertCount(0, $library['js']);
    $this->assertCount(1, $library['css']);
    $this->assertCount(0, $library['dependencies']);
    $this->assertEquals($path . '/css/example.css', $library['css'][0]['data']);
  }

  /**
   * Tests that a module with a missing library file results in FALSE.
   *
   * @covers ::buildByExtension
   */
  public function testBuildByExtensionWithMissingLibraryFile() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('example_module')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files_not_existing';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'example_module', $path);

    $this->assertSame($this->libraryDiscoveryParser->buildByExtension('example_module'), []);
  }

  /**
   * Tests that an exception is thrown when a libraries file couldn't be parsed.
   *
   * @covers ::buildByExtension
   */
  public function testInvalidLibrariesFile() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('invalid_file')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'invalid_file', $path);

    $this->setExpectedException(InvalidLibraryFileException::class);
    $this->libraryDiscoveryParser->buildByExtension('invalid_file');
  }

  /**
   * Tests that an exception is thrown when no CSS/JS/setting is specified.
   *
   * @covers ::buildByExtension
   */
  public function testBuildByExtensionWithMissingInformation() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('example_module_missing_information')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'example_module_missing_information', $path);

    $this->setExpectedException(IncompleteLibraryDefinitionException::class, "Incomplete library definition for definition 'example' in extension 'example_module_missing_information'");
    $this->libraryDiscoveryParser->buildByExtension('example_module_missing_information');
  }

  /**
   * Tests the version property, and how it propagates to the contained assets.
   *
   * @covers ::buildByExtension
   */
  public function testVersion() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('versions')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'versions', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('versions');

    $this->assertFalse(array_key_exists('version', $libraries['versionless']));
    $this->assertEquals(-1, $libraries['versionless']['css'][0]['version']);
    $this->assertEquals(-1, $libraries['versionless']['js'][0]['version']);

    $this->assertEquals('9.8.7.6', $libraries['versioned']['version']);
    $this->assertEquals('9.8.7.6', $libraries['versioned']['css'][0]['version']);
    $this->assertEquals('9.8.7.6', $libraries['versioned']['js'][0]['version']);

    $this->assertEquals(\Drupal::VERSION, $libraries['core-versioned']['version']);
    $this->assertEquals(\Drupal::VERSION, $libraries['core-versioned']['css'][0]['version']);
    $this->assertEquals(\Drupal::VERSION, $libraries['core-versioned']['js'][0]['version']);
  }

  /**
   * Tests that the version property of external libraries is handled.
   *
   * @covers ::buildByExtension
   */
  public function testExternalLibraries() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('external')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'external', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('external');
    $library = $libraries['example_external'];

    $this->assertEquals('http://example.com/css/example_external.css', $library['css'][0]['data']);
    $this->assertEquals('http://example.com/example_external.js', $library['js'][0]['data']);
    $this->assertEquals('3.14', $library['version']);
  }

  /**
   * Ensures that CSS weights are taken into account properly.
   *
   * @covers ::buildByExtension
   */
  public function testDefaultCssWeights() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('css_weights')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'css_weights', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('css_weights');
    $library = $libraries['example'];
    $css = $library['css'];
    $this->assertCount(10, $css);

    // The following default weights are tested:
    // - CSS_BASE: -200
    // - CSS_LAYOUT: -100
    // - CSS_COMPONENT: 0
    // - CSS_STATE: 100
    // - CSS_THEME: 200
    $this->assertEquals(200, $css[0]['weight']);
    $this->assertEquals(200 + 29, $css[1]['weight']);
    $this->assertEquals(-200, $css[2]['weight']);
    $this->assertEquals(-200 + 97, $css[3]['weight']);
    $this->assertEquals(-100, $css[4]['weight']);
    $this->assertEquals(-100 + 92, $css[5]['weight']);
    $this->assertEquals(0, $css[6]['weight']);
    $this->assertEquals(45, $css[7]['weight']);
    $this->assertEquals(100, $css[8]['weight']);
    $this->assertEquals(100 + 8, $css[9]['weight']);
  }

  /**
   * Ensures that you cannot provide positive weights for JavaScript libraries.
   *
   * @covers ::buildByExtension
   */
  public function testJsWithPositiveWeight() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('js_positive_weight')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'js_positive_weight', $path);

    $this->setExpectedException(\UnexpectedValueException::class);
    $this->libraryDiscoveryParser->buildByExtension('js_positive_weight');
  }

  /**
   * Tests a library with CSS/JavaScript and a setting.
   *
   * @covers ::buildByExtension
   */
  public function testLibraryWithCssJsSetting() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('css_js_settings')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'css_js_settings', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('css_js_settings');
    $library = $libraries['example'];

    // Ensures that the group and type are set automatically.
    $this->assertEquals(-100, $library['js'][0]['group']);
    $this->assertEquals('file', $library['js'][0]['type']);
    $this->assertEquals($path . '/js/example.js', $library['js'][0]['data']);

    $this->assertEquals(0, $library['css'][0]['group']);
    $this->assertEquals('file', $library['css'][0]['type']);
    $this->assertEquals($path . '/css/base.css', $library['css'][0]['data']);

    $this->assertEquals(['key' => 'value'], $library['drupalSettings']);
  }

  /**
   * Tests a library with dependencies.
   *
   * @covers ::buildByExtension
   */
  public function testLibraryWithDependencies() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('dependencies')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'dependencies', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('dependencies');
    $library = $libraries['example'];

    $this->assertCount(2, $library['dependencies']);
    $this->assertEquals('external/example_external', $library['dependencies'][0]);
    $this->assertEquals('example_module/example', $library['dependencies'][1]);
  }

  /**
   * Tests a library with a couple of data formats like full URL.
   *
   * @covers ::buildByExtension
   */
  public function testLibraryWithDataTypes() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('data_types')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'data_types', $path);

    $this->libraryDiscoveryParser->setFileValidUri('public://test.css', TRUE);
    $this->libraryDiscoveryParser->setFileValidUri('public://test2.css', FALSE);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('data_types');
    $library = $libraries['example'];

    $this->assertCount(5, $library['css']);
    $this->assertEquals('external', $library['css'][0]['type']);
    $this->assertEquals('http://example.com/test.css', $library['css'][0]['data']);
    $this->assertEquals('file', $library['css'][1]['type']);
    $this->assertEquals('tmp/test.css', $library['css'][1]['data']);
    $this->assertEquals('external', $library['css'][2]['type']);
    $this->assertEquals('//cdn.com/test.css', $library['css'][2]['data']);
    $this->assertEquals('file', $library['css'][3]['type']);
    $this->assertEquals('public://test.css', $library['css'][3]['data']);
  }

  /**
   * Tests a library with JavaScript-specific flags.
   *
   * @covers ::buildByExtension
   */
  public function testLibraryWithJavaScript() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('js')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'js', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('js');
    $library = $libraries['example'];

    $this->assertCount(2, $library['js']);
    $this->assertEquals(FALSE, $library['js'][0]['minified']);
    $this->assertEquals(TRUE, $library['js'][1]['minified']);
  }

  /**
   * Tests that an exception is thrown when license is missing when 3rd party.
   *
   * @covers ::buildByExtension
   */
  public function testLibraryThirdPartyWithMissingLicense() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('licenses_missing_information')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'licenses_missing_information', $path);

    $this->setExpectedException(LibraryDefinitionMissingLicenseException::class, "Missing license information in library definition for definition 'no-license-info-but-remote' extension 'licenses_missing_information': it has a remote, but no license.");
    $this->libraryDiscoveryParser->buildByExtension('licenses_missing_information');
  }

  /**
   * Tests a library with various licenses, some GPL-compatible, some not.
   *
   * @covers ::buildByExtension
   */
  public function testLibraryWithLicenses() {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with('licenses')
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', 'licenses', $path);

    $libraries = $this->libraryDiscoveryParser->buildByExtension('licenses');

    // For libraries without license info, the default license is applied.
    $library = $libraries['no-license-info'];
    $this->assertCount(1, $library['css']);
    $this->assertCount(1, $library['js']);
    $this->assertTrue(isset($library['license']));
    $default_license = [
      'name' => 'GNU-GPL-2.0-or-later',
      'url' => 'https://www.drupal.org/licensing/faq',
      'gpl-compatible' => TRUE,
    ];
    $this->assertEquals($library['license'], $default_license);

    // GPL2-licensed libraries.
    $library = $libraries['gpl2'];
    $this->assertCount(1, $library['css']);
    $this->assertCount(1, $library['js']);
    $expected_license = [
      'name' => 'gpl2',
      'url' => 'https://url-to-gpl2-license',
      'gpl-compatible' => TRUE,
    ];
    $this->assertEquals($library['license'], $expected_license);

    // MIT-licensed libraries.
    $library = $libraries['mit'];
    $this->assertCount(1, $library['css']);
    $this->assertCount(1, $library['js']);
    $expected_license = [
      'name' => 'MIT',
      'url' => 'https://url-to-mit-license',
      'gpl-compatible' => TRUE,
    ];
    $this->assertEquals($library['license'], $expected_license);

    // Libraries in the Public Domain.
    $library = $libraries['public-domain'];
    $this->assertCount(1, $library['css']);
    $this->assertCount(1, $library['js']);
    $expected_license = [
      'name' => 'Public Domain',
      'url' => 'https://url-to-public-domain-license',
      'gpl-compatible' => TRUE,
    ];
    $this->assertEquals($library['license'], $expected_license);

    // Apache-licensed libraries.
    $library = $libraries['apache'];
    $this->assertCount(1, $library['css']);
    $this->assertCount(1, $library['js']);
    $expected_license = [
      'name' => 'apache',
      'url' => 'https://url-to-apache-license',
      'gpl-compatible' => FALSE,
    ];
    $this->assertEquals($library['license'], $expected_license);

    // Copyrighted libraries.
    $library = $libraries['copyright'];
    $this->assertCount(1, $library['css']);
    $this->assertCount(1, $library['js']);
    $expected_license = [
      'name' => '© Some company',
      'gpl-compatible' => FALSE,
    ];
    $this->assertEquals($library['license'], $expected_license);
  }

  /**
   * Verifies assertions catch invalid CSS declarations.
   *
   * @dataProvider providerTestCssAssert
   */

  /**
   * Verify an assertion fails if CSS declarations have non-existent categories.
   *
   * @param string $extension
   *   The css extension to build.
   * @param string $exception_message
   *   The expected exception message.
   *
   * @dataProvider providerTestCssAssert
   */
  public function testCssAssert($extension, $exception_message) {
    $this->moduleHandler->expects($this->atLeastOnce())
      ->method('moduleExists')
      ->with($extension)
      ->will($this->returnValue(TRUE));

    $path = __DIR__ . '/library_test_files';
    $path = substr($path, strlen($this->root) + 1);
    $this->libraryDiscoveryParser->setPaths('module', $extension, $path);

    $this->setExpectedException(\AssertionError::class, $exception_message);
    $this->libraryDiscoveryParser->buildByExtension($extension);
  }

  /**
   * Data provider for testing bad CSS declarations.
   */
  public function providerTestCssAssert() {
    return [
      'css_bad_category' => ['css_bad_category', 'See https://www.drupal.org/node/2274843.'],
      'Improper CSS nesting' => ['css_bad_nesting', 'CSS must be nested under a category. See https://www.drupal.org/node/2274843.'],
      'Improper CSS nesting array' => ['css_bad_nesting_array', 'CSS files should be specified as key/value pairs, where the values are configuration options. See https://www.drupal.org/node/2274843.'],
    ];
  }

}

/**
 * Wraps the tested class to mock the external dependencies.
 */
class TestLibraryDiscoveryParser extends LibraryDiscoveryParser {

  protected $paths;

  protected $validUris;

  protected function drupalGetPath($type, $name) {
    return isset($this->paths[$type][$name]) ? $this->paths[$type][$name] : NULL;
  }

  public function setPaths($type, $name, $path) {
    $this->paths[$type][$name] = $path;
  }

  protected function fileValidUri($source) {
    return isset($this->validUris[$source]) ? $this->validUris[$source] : FALSE;
  }

  public function setFileValidUri($source, $valid) {
    $this->validUris[$source] = $valid;
  }

}

if (!defined('CSS_AGGREGATE_DEFAULT')) {
  define('CSS_AGGREGATE_DEFAULT', 0);
}
if (!defined('CSS_AGGREGATE_THEME')) {
  define('CSS_AGGREGATE_THEME', 100);
}
if (!defined('CSS_BASE')) {
  define('CSS_BASE', -200);
}
if (!defined('CSS_LAYOUT')) {
  define('CSS_LAYOUT', -100);
}
if (!defined('CSS_COMPONENT')) {
  define('CSS_COMPONENT', 0);
}
if (!defined('CSS_STATE')) {
  define('CSS_STATE', 100);
}
if (!defined('CSS_THEME')) {
  define('CSS_THEME', 200);
}
if (!defined('JS_SETTING')) {
  define('JS_SETTING', -200);
}
if (!defined('JS_LIBRARY')) {
  define('JS_LIBRARY', -100);
}
if (!defined('JS_DEFAULT')) {
  define('JS_DEFAULT', 0);
}
if (!defined('JS_THEME')) {
  define('JS_THEME', 100);
}
