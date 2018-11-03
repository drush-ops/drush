<?php

namespace Drupal\Tests\block\Kernel;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\block_test\Plugin\Block\TestHtmlBlock;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\block\Entity\Block;

/**
 * Tests the storage of blocks.
 *
 * @group block
 */
class BlockStorageUnitTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'block_test', 'system'];

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $controller;

  protected function setUp() {
    parent::setUp();

    $this->controller = $this->container->get('entity_type.manager')->getStorage('block');

    $this->container->get('theme_installer')->install(['stark']);
  }

  /**
   * Tests CRUD operations.
   */
  public function testBlockCRUD() {
    $this->assertTrue($this->controller instanceof ConfigEntityStorage, 'The block storage is loaded.');

    // Run each test method in the same installation.
    $this->createTests();
    $this->loadTests();
    $this->deleteTests();
  }

  /**
   * Tests the creation of blocks.
   */
  protected function createTests() {
    // Attempt to create a block without a plugin.
    try {
      $entity = $this->controller->create([]);
      $entity->getPlugin();
      $this->fail('A block without a plugin was created with no exception thrown.');
    }
    catch (PluginException $e) {
      $this->assertEqual('The block \'\' did not specify a plugin.', $e->getMessage(), 'An exception was thrown when a block was created without a plugin.');
    }

    // Create a block with only required values.
    $entity = $this->controller->create([
      'id' => 'test_block',
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => 'test_html',
    ]);
    $entity->save();

    $this->assertTrue($entity instanceof Block, 'The newly created entity is a Block.');

    // Verify all of the block properties.
    $actual_properties = $this->config('block.block.test_block')->get();
    $this->assertTrue(!empty($actual_properties['uuid']), 'The block UUID is set.');
    unset($actual_properties['uuid']);

    // Ensure that default values are filled in.
    $expected_properties = [
      'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
      'status' => TRUE,
      'dependencies' => ['module' => ['block_test'], 'theme' => ['stark']],
      'id' => 'test_block',
      'theme' => 'stark',
      'region' => 'content',
      'weight' => NULL,
      'provider' => NULL,
      'plugin' => 'test_html',
      'settings' => [
        'id' => 'test_html',
        'label' => '',
        'provider' => 'block_test',
        'label_display' => BlockPluginInterface::BLOCK_LABEL_VISIBLE,
      ],
      'visibility' => [],
    ];

    $this->assertIdentical($actual_properties, $expected_properties);

    $this->assertTrue($entity->getPlugin() instanceof TestHtmlBlock, 'The entity has an instance of the correct block plugin.');
  }

  /**
   * Tests the loading of blocks.
   */
  protected function loadTests() {
    $entity = $this->controller->load('test_block');

    $this->assertTrue($entity instanceof Block, 'The loaded entity is a Block.');

    // Verify several properties of the block.
    $this->assertSame('content', $entity->getRegion());
    $this->assertTrue($entity->status());
    $this->assertEqual($entity->getTheme(), 'stark');
    $this->assertTrue($entity->uuid());
  }

  /**
   * Tests the deleting of blocks.
   */
  protected function deleteTests() {
    $entity = $this->controller->load('test_block');

    // Ensure that the storage isn't currently empty.
    $config_storage = $this->container->get('config.storage');
    $config = $config_storage->listAll('block.block.');
    $this->assertFalse(empty($config), 'There are blocks in config storage.');

    // Delete the block.
    $entity->delete();

    // Ensure that the storage is now empty.
    $config = $config_storage->listAll('block.block.');
    $this->assertTrue(empty($config), 'There are no blocks in config storage.');
  }

  /**
   * Tests the installation of default blocks.
   */
  public function testDefaultBlocks() {
    \Drupal::service('theme_handler')->install(['classy']);
    $entities = $this->controller->loadMultiple();
    $this->assertTrue(empty($entities), 'There are no blocks initially.');

    // Install the block_test.module, so that its default config is installed.
    $this->installConfig(['block_test']);

    $entities = $this->controller->loadMultiple();
    $entity = reset($entities);
    $this->assertEqual($entity->id(), 'test_block', 'The default test block was loaded.');
  }

}
