<?php

namespace Drupal\Tests\simpletest\Unit;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\simpletest\Exception\MissingGroupException;
use Drupal\simpletest\TestDiscovery;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\simpletest\TestDiscovery
 * @group simpletest
 */
class TestDiscoveryTest extends UnitTestCase {

  /**
   * @covers ::getTestInfo
   * @dataProvider infoParserProvider
   */
  public function testTestInfoParser($expected, $classname, $doc_comment = NULL) {
    $info = TestDiscovery::getTestInfo($classname, $doc_comment);
    $this->assertEquals($expected, $info);
  }

  public function infoParserProvider() {
    // A module provided unit test.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\Tests\simpletest\Unit\TestDiscoveryTest',
        'group' => 'simpletest',
        'description' => 'Tests \Drupal\simpletest\TestDiscovery.',
        'type' => 'PHPUnit-Unit',
      ],
      // Classname.
      'Drupal\Tests\simpletest\Unit\TestDiscoveryTest',
    ];

    // A core unit test.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\Tests\Core\DrupalTest',
        'group' => 'DrupalTest',
        'description' => 'Tests \Drupal.',
        'type' => 'PHPUnit-Unit',
      ],
      // Classname.
      'Drupal\Tests\Core\DrupalTest',
    ];

    // Functional PHPUnit test.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\FunctionalTests\BrowserTestBaseTest',
        'group' => 'browsertestbase',
        'description' => 'Tests BrowserTestBase functionality.',
        'type' => 'PHPUnit-Functional',
      ],
      // Classname.
      'Drupal\FunctionalTests\BrowserTestBaseTest',
    ];

    // kernel PHPUnit test.
    $tests['phpunit-kernel'] = [
      // Expected result.
      [
        'name' => '\Drupal\Tests\file\Kernel\FileItemValidationTest',
        'group' => 'file',
        'description' => 'Tests that files referenced in file and image fields are always validated.',
        'type' => 'PHPUnit-Kernel',
      ],
      // Classname.
      '\Drupal\Tests\file\Kernel\FileItemValidationTest',
    ];

    // Simpletest classes can not be autoloaded in a PHPUnit test, therefore
    // provide a docblock.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'group' => 'simpletest',
        'description' => 'Tests the Simpletest UI internal browser.',
        'type' => 'Simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
 * Tests the Simpletest UI internal browser.
 *
 * @group simpletest
 */
 ",
    ];

    // Test with a different amount of leading spaces.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'group' => 'simpletest',
        'description' => 'Tests the Simpletest UI internal browser.',
        'type' => 'Simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
   * Tests the Simpletest UI internal browser.
   *
   * @group simpletest
   */
   */
 ",
    ];

    // Make sure that a "* @" inside a string does not get parsed as an
    // annotation.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'group' => 'simpletest',
        'description' => 'Tests the Simpletest UI internal browser. * @',
        'type' => 'Simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
   * Tests the Simpletest UI internal browser. * @
   *
   * @group simpletest
   */
 ",
    ];

    // Multiple @group annotations.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'group' => 'Test',
        'description' => 'Tests the Simpletest UI internal browser.',
        'type' => 'Simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
 * Tests the Simpletest UI internal browser.
 *
 * @group Test
 * @group simpletest
 */
 ",
    ];

    // @dependencies annotation.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'description' => 'Tests the Simpletest UI internal browser.',
        'type' => 'Simpletest',
        'requires' => ['module' => ['test']],
        'group' => 'simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
 * Tests the Simpletest UI internal browser.
 *
 * @dependencies test
 * @group simpletest
 */
 ",
    ];

    // Multiple @dependencies annotation.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'description' => 'Tests the Simpletest UI internal browser.',
        'type' => 'Simpletest',
        'requires' => ['module' => ['test', 'test1', 'test2']],
        'group' => 'simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
 * Tests the Simpletest UI internal browser.
 *
 * @dependencies test, test1, test2
 * @group simpletest
 */
 ",
    ];

    // Multi-line summary line.
    $tests[] = [
      // Expected result.
      [
        'name' => 'Drupal\simpletest\Tests\ExampleSimpleTest',
        'description' => 'Tests the Simpletest UI internal browser. And the summary line continues an there is no gap to the annotation.',
        'type' => 'Simpletest',
        'group' => 'simpletest',
      ],
      // Classname.
      'Drupal\simpletest\Tests\ExampleSimpleTest',
      // Doc block.
      "/**
 * Tests the Simpletest UI internal browser. And the summary line continues an
 * there is no gap to the annotation.
 *
 * @group simpletest
 */
 ",
    ];
    return $tests;
  }

  /**
   * @covers ::getTestInfo
   */
  public function testTestInfoParserMissingGroup() {
    $classname = 'Drupal\KernelTests\field\BulkDeleteTest';
    $doc_comment = <<<EOT
/**
 * Bulk delete storages and fields, and clean up afterwards.
 */
EOT;
    $this->setExpectedException(MissingGroupException::class, 'Missing @group annotation in Drupal\KernelTests\field\BulkDeleteTest');
    TestDiscovery::getTestInfo($classname, $doc_comment);
  }

  /**
   * @covers ::getTestInfo
   */
  public function testTestInfoParserMissingSummary() {
    $classname = 'Drupal\KernelTests\field\BulkDeleteTest';
    $doc_comment = <<<EOT
/**
 * @group field
 */
EOT;
    $info = TestDiscovery::getTestInfo($classname, $doc_comment);
    $this->assertEmpty($info['description']);
  }

  protected function setupVfsWithTestClasses() {
    vfsStream::setup('drupal');

    $test_file = <<<EOF
<?php

/**
 * Test description
 * @group example
 */
class FunctionalExampleTest {}
EOF;

    vfsStream::create([
      'modules' => [
        'test_module' => [
          'tests' => [
            'src' => [
              'Functional' => [
                'FunctionalExampleTest.php' => $test_file,
                'FunctionalExampleTest2.php' => str_replace(['FunctionalExampleTest', '@group example'], ['FunctionalExampleTest2', '@group example2'], $test_file),
              ],
              'Kernel' => [
                'KernelExampleTest3.php' => str_replace(['FunctionalExampleTest', '@group example'], ['KernelExampleTest3', '@group example2'], $test_file),
                'KernelExampleTestBase.php' => str_replace(['FunctionalExampleTest', '@group example'], ['KernelExampleTestBase', '@group example2'], $test_file),
                'KernelExampleTrait.php' => str_replace(['FunctionalExampleTest', '@group example'], ['KernelExampleTrait', '@group example2'], $test_file),
                'KernelExampleInterface.php' => str_replace(['FunctionalExampleTest', '@group example'], ['KernelExampleInterface', '@group example2'], $test_file),
              ],
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * @covers ::getTestClasses
   */
  public function testGetTestClasses() {
    $this->setupVfsWithTestClasses();
    $class_loader = $this->prophesize(ClassLoader::class);
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);

    $test_discovery = new TestTestDiscovery('vfs://drupal', $class_loader->reveal(), $module_handler->reveal());

    $extensions = [
      'test_module' => new Extension('vfs://drupal', 'module', 'modules/test_module/test_module.info.yml'),
    ];
    $test_discovery->setExtensions($extensions);
    $result = $test_discovery->getTestClasses();
    $this->assertCount(2, $result);
    $this->assertEquals([
      'example' => [
        'Drupal\Tests\test_module\Functional\FunctionalExampleTest' => [
          'name' => 'Drupal\Tests\test_module\Functional\FunctionalExampleTest',
          'description' => 'Test description',
          'group' => 'example',
          'type' => 'PHPUnit-Functional',
        ],
      ],
      'example2' => [
        'Drupal\Tests\test_module\Functional\FunctionalExampleTest2' => [
          'name' => 'Drupal\Tests\test_module\Functional\FunctionalExampleTest2',
          'description' => 'Test description',
          'group' => 'example2',
          'type' => 'PHPUnit-Functional',
        ],
        'Drupal\Tests\test_module\Kernel\KernelExampleTest3' => [
          'name' => 'Drupal\Tests\test_module\Kernel\KernelExampleTest3',
          'description' => 'Test description',
          'group' => 'example2',
          'type' => 'PHPUnit-Kernel',
        ],
      ],
    ], $result);
  }

  /**
   * @covers ::getTestClasses
   */
  public function testGetTestClassesWithSelectedTypes() {
    $this->setupVfsWithTestClasses();
    $class_loader = $this->prophesize(ClassLoader::class);
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);

    $test_discovery = new TestTestDiscovery('vfs://drupal', $class_loader->reveal(), $module_handler->reveal());

    $extensions = [
      'test_module' => new Extension('vfs://drupal', 'module', 'modules/test_module/test_module.info.yml'),
    ];
    $test_discovery->setExtensions($extensions);
    $result = $test_discovery->getTestClasses(NULL, ['PHPUnit-Kernel']);
    $this->assertCount(2, $result);
    $this->assertEquals([
      'example' => [],
      'example2' => [
        'Drupal\Tests\test_module\Kernel\KernelExampleTest3' => [
          'name' => 'Drupal\Tests\test_module\Kernel\KernelExampleTest3',
          'description' => 'Test description',
          'group' => 'example2',
          'type' => 'PHPUnit-Kernel',
        ],
      ],
    ], $result);
  }

  /**
   * @covers ::getPhpunitTestSuite
   * @dataProvider providerTestGetPhpunitTestSuite
   */
  public function testGetPhpunitTestSuite($classname, $expected) {
    $this->assertEquals($expected, TestDiscovery::getPhpunitTestSuite($classname));
  }

  public function providerTestGetPhpunitTestSuite() {
    $data = [];
    $data['simpletest-webtest'] = ['\Drupal\rest\Tests\NodeTest', FALSE];
    $data['simpletest-kerneltest'] = ['\Drupal\hal\Tests\FileNormalizeTest', FALSE];
    $data['module-unittest'] = [static::class, 'Unit'];
    $data['module-kerneltest'] = ['\Drupal\KernelTests\Core\Theme\TwigMarkupInterfaceTest', 'Kernel'];
    $data['module-functionaltest'] = ['\Drupal\FunctionalTests\BrowserTestBaseTest', 'Functional'];
    $data['module-functionaljavascripttest'] = ['\Drupal\Tests\toolbar\FunctionalJavascript\ToolbarIntegrationTest', 'FunctionalJavascript'];
    $data['core-unittest'] = ['\Drupal\Tests\ComposerIntegrationTest', 'Unit'];
    $data['core-unittest2'] = ['Drupal\Tests\Core\DrupalTest', 'Unit'];
    $data['core-kerneltest'] = ['\Drupal\KernelTests\KernelTestBaseTest', 'Kernel'];
    $data['core-functionaltest'] = ['\Drupal\FunctionalTests\ExampleTest', 'Functional'];
    $data['core-functionaljavascripttest'] = ['\Drupal\FunctionalJavascriptTests\ExampleTest', 'FunctionalJavascript'];

    return $data;
  }

  /**
   * Ensure that classes are not reflected when the docblock is empty.
   *
   * @covers ::getTestInfo
   */
  public function testGetTestInfoEmptyDocblock() {
    // If getTestInfo() performed reflection, it won't be able to find the
    // class we asked it to analyze, so it will throw a ReflectionException.
    // We want to make sure it didn't do that, because we already did some
    // analysis and already have an empty docblock. getTestInfo() will throw
    // MissingGroupException because the annotation is empty.
    $this->setExpectedException(MissingGroupException::class);
    TestDiscovery::getTestInfo('Drupal\Tests\simpletest\ThisTestDoesNotExistTest', '');
  }

  /**
   * Ensure TestDiscovery::scanDirectory() ignores certain abstract file types.
   *
   * @covers ::scanDirectory
   */
  public function testScanDirectoryNoAbstract() {
    $this->setupVfsWithTestClasses();
    $files = TestDiscovery::scanDirectory('Drupal\\Tests\\test_module\\Kernel\\', vfsStream::url('drupal/modules/test_module/tests/src/Kernel'));
    $this->assertNotEmpty($files);
    $this->assertArrayNotHasKey('Drupal\Tests\test_module\Kernel\KernelExampleTestBase', $files);
    $this->assertArrayNotHasKey('Drupal\Tests\test_module\Kernel\KernelExampleTrait', $files);
    $this->assertArrayNotHasKey('Drupal\Tests\test_module\Kernel\KernelExampleInterface', $files);
    $this->assertArrayHasKey('Drupal\Tests\test_module\Kernel\KernelExampleTest3', $files);
  }

}

class TestTestDiscovery extends TestDiscovery {

  /**
   * @var \Drupal\Core\Extension\Extension[]
   */
  protected $extensions = [];

  public function setExtensions(array $extensions) {
    $this->extensions = $extensions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtensions() {
    return $this->extensions;
  }

}

namespace Drupal\simpletest\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the Simpletest UI internal browser.
 *
 * @group simpletest
 */
class ExampleSimpleTest extends WebTestBase {
}
