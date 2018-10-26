<?php

namespace Drupal\Tests\devel_generate;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\devel_generate\DevelGeneratePluginManager;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Language\Language;

if (!defined('DRUPAL_ROOT')) {
  //Looping to find drupal root folder.
  $current_dir = dirname(__DIR__);
  while (!file_exists("$current_dir/index.php")) {
    $current_dir = dirname($current_dir);
  }
  define('DRUPAL_ROOT', $current_dir);
}

/**
 * @coversDefaultClass \Drupal\devel_generate\DevelGeneratePluginManager
 * @group devel_generate
 */
class DevelGenerateManagerTest extends UnitTestCase {

  /**
   * The plugin discovery.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $discovery;

  /**
   * A list of devel generate plugin definitions.
   *
   * @var array
   */
  protected $definitions = array(
    'devel_generate_example' => array(
      'id' => 'devel_generate_example',
      'class' => 'Drupal\devel_generate_example\Plugin\DevelGenerate\ExampleDevelGenerate',
      'url' => 'devel_generate_example',
    ),
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Mock a Discovery object to replace AnnotationClassDiscovery.
    $this->discovery = $this->getMock('Drupal\Component\Plugin\Discovery\DiscoveryInterface');
    $this->discovery->expects($this->any())
      ->method('getDefinitions')
      ->will($this->returnValue($this->definitions));

  }

  /**
   * Test creating an instance of the DevelGenerateManager.
   */
  public function testCreateInstance() {
    $namespaces = new \ArrayObject(array('Drupal\devel_generate_example' => realpath(dirname(__FILE__) . '/../../../modules/devel_generate_example/lib')));
    $cache_backend = $this->getMock('Drupal\Core\Cache\CacheBackendInterface');

    $module_handler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $manager = new TestDevelGeneratePluginManager($namespaces, $cache_backend, $module_handler);
    $manager->setDiscovery($this->discovery);

    $example_instance = $manager->createInstance('devel_generate_example');
    $plugin_def = $example_instance->getPluginDefinition();

    $this->assertInstanceOf('Drupal\devel_generate_example\Plugin\DevelGenerate\ExampleDevelGenerate', $example_instance);
    $this->assertArrayHasKey('url', $plugin_def);
    $this->assertTrue($plugin_def['url'] == 'devel_generate_example');
  }

}

/**
 * Provides a testing version of DevelGeneratePluginManager with an empty
 * constructor.
 */
class TestDevelGeneratePluginManager extends DevelGeneratePluginManager {
  /**
   * Sets the discovery for the manager.
   *
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The discovery object.
   */
  public function setDiscovery(DiscoveryInterface $discovery) {
    $this->discovery = $discovery;
  }
}
