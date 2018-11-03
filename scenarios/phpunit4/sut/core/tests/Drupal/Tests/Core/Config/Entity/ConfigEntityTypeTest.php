<?php

namespace Drupal\Tests\Core\Config\Entity;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Config\Entity\Exception\ConfigEntityStorageClassException;

/**
 * @coversDefaultClass \Drupal\Core\Config\Entity\ConfigEntityType
 * @group Config
 */
class ConfigEntityTypeTest extends UnitTestCase {

  /**
   * The mocked typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $typedConfigManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->typedConfigManager = $this->getMock(TypedConfigManagerInterface::class);
    $container = new ContainerBuilder();
    $container->set('config.typed', $this->typedConfigManager);
    \Drupal::setContainer($container);
  }

  /**
   * Sets up a ConfigEntityType object for a given set of values.
   *
   * @param array $definition
   *   An array of values to use for the ConfigEntityType.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityTypeInterface
   */
  protected function setUpConfigEntityType($definition) {
    if (!isset($definition['id'])) {
      $definition += [
        'id' => 'example_config_entity_type',
      ];
    }
    return new ConfigEntityType($definition);
  }

  /**
   * Tests that we get an exception when the length of the config prefix that is
   * returned by getConfigPrefix() exceeds the maximum defined prefix length.
   *
   * @covers ::getConfigPrefix
   */
  public function testConfigPrefixLengthExceeds() {
    // A provider length of 24 and config_prefix length of 59 (+1 for the .)
    // results in a config length of 84, which is too long.
    $definition = [
      'provider' => $this->randomMachineName(24),
      'config_prefix' => $this->randomMachineName(59),
    ];
    $config_entity = $this->setUpConfigEntityType($definition);
    $this->setExpectedException(
      '\Drupal\Core\Config\ConfigPrefixLengthException',
      "The configuration file name prefix {$definition['provider']}.{$definition['config_prefix']} exceeds the maximum character limit of " . ConfigEntityType::PREFIX_LENGTH
    );
    $this->assertEmpty($config_entity->getConfigPrefix());
  }

  /**
   * Tests that a valid config prefix returned by getConfigPrefix()
   * does not throw an exception and is formatted as expected.
   *
   * @covers ::getConfigPrefix
   */
  public function testConfigPrefixLengthValid() {
    // A provider length of 24 and config_prefix length of 58 (+1 for the .)
    // results in a config length of 83, which is right at the limit.
    $definition = [
      'provider' => $this->randomMachineName(24),
      'config_prefix' => $this->randomMachineName(58),
    ];
    $config_entity = $this->setUpConfigEntityType($definition);
    $expected_prefix = $definition['provider'] . '.' . $definition['config_prefix'];
    $this->assertEquals($expected_prefix, $config_entity->getConfigPrefix());
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $config_entity = new ConfigEntityType([
      'id' => 'example_config_entity_type',
    ]);
    $this->assertEquals('Drupal\Core\Config\Entity\ConfigEntityStorage', $config_entity->getStorageClass());
  }

  /**
   * @covers ::__construct
   */
  public function testConstructBadStorage() {
    $this->setExpectedException(ConfigEntityStorageClassException::class, '\Drupal\Core\Entity\KeyValueStore\KeyValueEntityStorage is not \Drupal\Core\Config\Entity\ConfigEntityStorage or it does not extend it');
    new ConfigEntityType([
      'id' => 'example_config_entity_type',
      'handlers' => ['storage' => '\Drupal\Core\Entity\KeyValueStore\KeyValueEntityStorage'],
    ]);
  }

  /**
   * @covers ::setStorageClass
   */
  public function testSetStorageClass() {
    $config_entity = $this->setUpConfigEntityType([]);
    $this->setExpectedException(ConfigEntityStorageClassException::class, '\Drupal\Core\Entity\KeyValueStore\KeyValueEntityStorage is not \Drupal\Core\Config\Entity\ConfigEntityStorage or it does not extend it');
    $config_entity->setStorageClass('\Drupal\Core\Entity\KeyValueStore\KeyValueEntityStorage');
  }

  /**
   * Tests the getConfigPrefix() method.
   *
   * @dataProvider providerTestGetConfigPrefix
   *
   * @covers ::getConfigPrefix
   */
  public function testGetConfigPrefix($definition, $expected) {
    $entity_type = $this->setUpConfigEntityType($definition);
    $this->assertSame($expected, $entity_type->getConfigPrefix());
  }

  /**
   * Provides test data.
   */
  public function providerTestGetConfigPrefix() {
    return [
      [['provider' => 'node', 'id' => 'node_type', 'config_prefix' => 'type'], 'node.type'],
      [['provider' => 'views', 'id' => 'view'], 'views.view'],
    ];
  }

  /**
   * @covers ::getPropertiesToExport
   *
   * @dataProvider providerGetPropertiesToExport
   */
  public function testGetPropertiesToExport($definition, $expected) {
    $entity_type = $this->setUpConfigEntityType($definition);
    $properties_to_export = $entity_type->getPropertiesToExport();
    $this->assertSame($expected, $properties_to_export);

    // Ensure the method is idempotent.
    $properties_to_export = $entity_type->getPropertiesToExport();
    $this->assertSame($expected, $properties_to_export);
  }

  public function providerGetPropertiesToExport() {
    $data = [];
    $data[] = [
      [
        'config_export' => [
          'id',
          'custom_property' => 'customProperty',
        ],
      ],
      [
        'uuid' => 'uuid',
        'langcode' => 'langcode',
        'status' => 'status',
        'dependencies' => 'dependencies',
        'third_party_settings' => 'third_party_settings',
        '_core' => '_core',
        'id' => 'id',
        'custom_property' => 'customProperty',
      ],
    ];

    $data[] = [
      [
        'config_export' => [
          'id',
        ],
        'mergedConfigExport' => [
          'random_key' => 'random_key',
        ],
      ],
      [
        'random_key' => 'random_key',
      ],
    ];
    return $data;
  }

  /**
   * @covers ::getPropertiesToExport
   */
  public function testGetPropertiesToExportSchemaFallback() {
    $this->typedConfigManager->expects($this->once())
      ->method('getDefinition')
      ->will($this->returnValue(['mapping' => ['id' => '', 'dependencies' => '']]));
    $config_entity_type = new ConfigEntityType([
      'id' => 'example_config_entity_type',
    ]);
    $this->assertEquals(['id' => 'id', 'dependencies' => 'dependencies'], $config_entity_type->getPropertiesToExport('test'));
  }

  /**
   * @covers ::getPropertiesToExport
   */
  public function testGetPropertiesToExportNoFallback() {
    $config_entity_type = new ConfigEntityType([
      'id' => 'example_config_entity_type',
    ]);
    $this->assertNull($config_entity_type->getPropertiesToExport());
  }

}
