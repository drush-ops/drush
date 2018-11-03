<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Entity\EntityFieldManagerTest.
 */

namespace Drupal\Tests\Core\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\Core\Entity\EntityFieldManager
 * @group Entity
 */
class EntityFieldManagerTest extends UnitTestCase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $typedDataManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $moduleHandler;

  /**
   * The cache backend to use.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $cacheBackend;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $languageManager;

  /**
   * The keyvalue factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $keyValueFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeRepository;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity field manager under test.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The dependency injection container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $container;

  /**
   * The entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $entityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->container = $this->prophesize(ContainerInterface::class);
    \Drupal::setContainer($this->container->reveal());

    $this->typedDataManager = $this->prophesize(TypedDataManagerInterface::class);
    $this->typedDataManager->getDefinition('field_item:boolean')->willReturn([
      'class' => BooleanItem::class,
    ]);
    $this->container->get('typed_data_manager')->willReturn($this->typedDataManager->reveal());

    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->moduleHandler->alter('entity_base_field_info', Argument::type('array'), Argument::any())->willReturn(NULL);
    $this->moduleHandler->alter('entity_bundle_field_info', Argument::type('array'), Argument::any(), Argument::type('string'))->willReturn(NULL);

    $this->cacheBackend = $this->prophesize(CacheBackendInterface::class);
    $this->cacheTagsInvalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);

    $language = new Language(['id' => 'en']);
    $this->languageManager = $this->prophesize(LanguageManagerInterface::class);
    $this->languageManager->getCurrentLanguage()->willReturn($language);
    $this->languageManager->getLanguages()->willReturn(['en' => (object) ['id' => 'en']]);

    $this->keyValueFactory = $this->prophesize(KeyValueFactoryInterface::class);

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeRepository = $this->prophesize(EntityTypeRepositoryInterface::class);
    $this->entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $this->entityDisplayRepository = $this->prophesize(EntityDisplayRepositoryInterface::class);

    $this->entityFieldManager = new TestEntityFieldManager($this->entityTypeManager->reveal(), $this->entityTypeBundleInfo->reveal(), $this->entityDisplayRepository->reveal(), $this->typedDataManager->reveal(), $this->languageManager->reveal(), $this->keyValueFactory->reveal(), $this->moduleHandler->reveal(), $this->cacheBackend->reveal());
  }

  /**
   * Sets up the entity type manager to be tested.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[]|\Prophecy\Prophecy\ProphecyInterface[] $definitions
   *   (optional) An array of entity type definitions.
   */
  protected function setUpEntityTypeDefinitions($definitions = []) {
    $class = $this->getMockClass(EntityInterface::class);
    foreach ($definitions as $key => $entity_type) {
      // \Drupal\Core\Entity\EntityTypeInterface::getLinkTemplates() is called
      // by \Drupal\Core\Entity\EntityManager::processDefinition() so it must
      // always be mocked.
      $entity_type->getLinkTemplates()->willReturn([]);

      // Give the entity type a legitimate class to return.
      $entity_type->getClass()->willReturn($class);

      $definitions[$key] = $entity_type->reveal();
    }

    $this->entityTypeManager->getDefinition(Argument::type('string'))
      ->will(function ($args) use ($definitions) {
        if (isset($definitions[$args[0]])) {
          return $definitions[$args[0]];
        }
        throw new PluginNotFoundException($args[0]);
      });
    $this->entityTypeManager->getDefinition(Argument::type('string'), FALSE)
      ->will(function ($args) use ($definitions) {
        if (isset($definitions[$args[0]])) {
          return $definitions[$args[0]];
        }
      });
    $this->entityTypeManager->getDefinitions()->willReturn($definitions);

  }

  /**
   * Tests the getBaseFieldDefinitions() method.
   *
   * @covers ::getBaseFieldDefinitions
   * @covers ::buildBaseFieldDefinitions
   */
  public function testGetBaseFieldDefinitions() {
    $field_definition = $this->setUpEntityWithFieldDefinition();

    $expected = ['id' => $field_definition];
    $this->assertSame($expected, $this->entityFieldManager->getBaseFieldDefinitions('test_entity_type'));
  }

  /**
   * Tests the getFieldDefinitions() method.
   *
   * @covers ::getFieldDefinitions
   * @covers ::buildBundleFieldDefinitions
   */
  public function testGetFieldDefinitions() {
    $field_definition = $this->setUpEntityWithFieldDefinition();

    $expected = ['id' => $field_definition];
    $this->assertSame($expected, $this->entityFieldManager->getFieldDefinitions('test_entity_type', 'test_entity_bundle'));
  }

  /**
   * Tests the getFieldStorageDefinitions() method.
   *
   * @covers ::getFieldStorageDefinitions
   * @covers ::buildFieldStorageDefinitions
   */
  public function testGetFieldStorageDefinitions() {
    $field_definition = $this->setUpEntityWithFieldDefinition(TRUE);
    $field_storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage_definition->getName()->willReturn('field_storage');

    $definitions = ['field_storage' => $field_storage_definition->reveal()];

    $this->moduleHandler->getImplementations('entity_base_field_info')->willReturn([]);
    $this->moduleHandler->getImplementations('entity_field_storage_info')->willReturn(['example_module']);
    $this->moduleHandler->invoke('example_module', 'entity_field_storage_info', [$this->entityType])->willReturn($definitions);
    $this->moduleHandler->alter('entity_field_storage_info', $definitions, $this->entityType)->willReturn(NULL);

    $expected = [
      'id' => $field_definition,
      'field_storage' => $field_storage_definition->reveal(),
    ];
    $this->assertSame($expected, $this->entityFieldManager->getFieldStorageDefinitions('test_entity_type'));
  }

  /**
   * Tests the getBaseFieldDefinitions() method with a translatable entity type.
   *
   * @covers ::getBaseFieldDefinitions
   * @covers ::buildBaseFieldDefinitions
   *
   * @dataProvider providerTestGetBaseFieldDefinitionsTranslatableEntityTypeDefaultLangcode
   */
  public function testGetBaseFieldDefinitionsTranslatableEntityTypeDefaultLangcode($default_langcode_key) {
    $this->setUpEntityWithFieldDefinition(FALSE, 'id', ['langcode' => 'langcode', 'default_langcode' => $default_langcode_key]);

    $field_definition = $this->prophesize()->willImplement(FieldDefinitionInterface::class)->willImplement(FieldStorageDefinitionInterface::class);
    $field_definition->isTranslatable()->willReturn(TRUE);

    $entity_class = EntityManagerTestEntity::class;
    $entity_class::$baseFieldDefinitions += ['langcode' => $field_definition];

    $this->entityType->isTranslatable()->willReturn(TRUE);

    $definitions = $this->entityFieldManager->getBaseFieldDefinitions('test_entity_type');

    $this->assertTrue(isset($definitions[$default_langcode_key]));
  }

  /**
   * Provides test data for testGetBaseFieldDefinitionsTranslatableEntityTypeDefaultLangcode().
   *
   * @return array
   *   Test data.
   */
  public function providerTestGetBaseFieldDefinitionsTranslatableEntityTypeDefaultLangcode() {
    return [
      ['default_langcode'],
      ['custom_default_langcode_key'],
    ];
  }

  /**
   * Tests the getBaseFieldDefinitions() method with a translatable entity type.
   *
   * @covers ::getBaseFieldDefinitions
   * @covers ::buildBaseFieldDefinitions
   *
   * @dataProvider providerTestGetBaseFieldDefinitionsTranslatableEntityTypeLangcode
   */
  public function testGetBaseFieldDefinitionsTranslatableEntityTypeLangcode($provide_key, $provide_field, $translatable) {
    $keys = $provide_key ? ['langcode' => 'langcode'] : [];
    $this->setUpEntityWithFieldDefinition(FALSE, 'id', $keys);

    if ($provide_field) {
      $field_definition = $this->prophesize()->willImplement(FieldDefinitionInterface::class)->willImplement(FieldStorageDefinitionInterface::class);
      $field_definition->isTranslatable()->willReturn($translatable);
      if (!$translatable) {
        $field_definition->setTranslatable(!$translatable)->shouldBeCalled();
      }

      $entity_class = EntityManagerTestEntity::class;
      $entity_class::$baseFieldDefinitions += ['langcode' => $field_definition->reveal()];
    }

    $this->entityType->isTranslatable()->willReturn(TRUE);
    $this->entityType->getLabel()->willReturn('Test');

    $this->setExpectedException(\LogicException::class, 'The Test entity type cannot be translatable as it does not define a translatable "langcode" field.');
    $this->entityFieldManager->getBaseFieldDefinitions('test_entity_type');
  }

  /**
   * Provides test data for testGetBaseFieldDefinitionsTranslatableEntityTypeLangcode().
   *
   * @return array
   *   Test data.
   */
  public function providerTestGetBaseFieldDefinitionsTranslatableEntityTypeLangcode() {
    return [
      [FALSE, TRUE, TRUE],
      [TRUE, FALSE, TRUE],
      [TRUE, TRUE, FALSE],
    ];
  }

  /**
   * Tests the getBaseFieldDefinitions() method with caching.
   *
   * @covers ::getBaseFieldDefinitions
   */
  public function testGetBaseFieldDefinitionsWithCaching() {
    $field_definition = $this->setUpEntityWithFieldDefinition();

    $expected = ['id' => $field_definition];

    $this->cacheBackend->get('entity_base_field_definitions:test_entity_type:en')
      ->willReturn(FALSE)
      ->shouldBeCalled();
    $this->cacheBackend->set('entity_base_field_definitions:test_entity_type:en', Argument::any(), Cache::PERMANENT, ['entity_types', 'entity_field_info'])
      ->will(function ($args) {
        $data = (object) ['data' => $args[1]];
        $this->get('entity_base_field_definitions:test_entity_type:en')
          ->willReturn($data)
          ->shouldBeCalled();
      })
      ->shouldBeCalled();

    $this->assertSame($expected, $this->entityFieldManager->getBaseFieldDefinitions('test_entity_type'));
    $this->entityFieldManager->testClearEntityFieldInfo();
    $this->assertSame($expected, $this->entityFieldManager->getBaseFieldDefinitions('test_entity_type'));
  }

  /**
   * Tests the getFieldDefinitions() method with caching.
   *
   * @covers ::getFieldDefinitions
   */
  public function testGetFieldDefinitionsWithCaching() {
    $field_definition = $this->setUpEntityWithFieldDefinition(FALSE, 'id');

    $expected = ['id' => $field_definition];

    $this->cacheBackend->get('entity_base_field_definitions:test_entity_type:en')
      ->willReturn((object) ['data' => $expected])
      ->shouldBeCalledTimes(2);
    $this->cacheBackend->get('entity_bundle_field_definitions:test_entity_type:test_bundle:en')
      ->willReturn(FALSE)
      ->shouldBeCalledTimes(1);
    $this->cacheBackend->set('entity_bundle_field_definitions:test_entity_type:test_bundle:en', Argument::any(), Cache::PERMANENT, ['entity_types', 'entity_field_info'])
      ->will(function ($args) {
        $data = (object) ['data' => $args[1]];
        $this->get('entity_bundle_field_definitions:test_entity_type:test_bundle:en')
          ->willReturn($data)
          ->shouldBeCalled();
      })
      ->shouldBeCalled();

    $this->assertSame($expected, $this->entityFieldManager->getFieldDefinitions('test_entity_type', 'test_bundle'));
    $this->entityFieldManager->testClearEntityFieldInfo();
    $this->assertSame($expected, $this->entityFieldManager->getFieldDefinitions('test_entity_type', 'test_bundle'));
  }

  /**
   * Tests the getFieldStorageDefinitions() method with caching.
   *
   * @covers ::getFieldStorageDefinitions
   */
  public function testGetFieldStorageDefinitionsWithCaching() {
    $field_definition = $this->setUpEntityWithFieldDefinition(TRUE, 'id');
    $field_storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage_definition->getName()->willReturn('field_storage');

    $definitions = ['field_storage' => $field_storage_definition->reveal()];

    $this->moduleHandler->getImplementations('entity_field_storage_info')->willReturn(['example_module']);
    $this->moduleHandler->invoke('example_module', 'entity_field_storage_info', [$this->entityType])->willReturn($definitions);
    $this->moduleHandler->alter('entity_field_storage_info', $definitions, $this->entityType)->willReturn(NULL);

    $expected = [
      'id' => $field_definition,
      'field_storage' => $field_storage_definition->reveal(),
    ];

    $this->cacheBackend->get('entity_base_field_definitions:test_entity_type:en')
      ->willReturn((object) ['data' => ['id' => $expected['id']]])
      ->shouldBeCalledTimes(2);
    $this->cacheBackend->get('entity_field_storage_definitions:test_entity_type:en')->willReturn(FALSE);

    $this->cacheBackend->set('entity_field_storage_definitions:test_entity_type:en', Argument::any(), Cache::PERMANENT, ['entity_types', 'entity_field_info'])
      ->will(function () use ($expected) {
        $this->get('entity_field_storage_definitions:test_entity_type:en')
          ->willReturn((object) ['data' => $expected])
          ->shouldBeCalled();
      })
      ->shouldBeCalled();

    $this->assertSame($expected, $this->entityFieldManager->getFieldStorageDefinitions('test_entity_type'));
    $this->entityFieldManager->testClearEntityFieldInfo();
    $this->assertSame($expected, $this->entityFieldManager->getFieldStorageDefinitions('test_entity_type'));
  }

  /**
   * Tests the getBaseFieldDefinitions() method with an invalid definition.
   *
   * @covers ::getBaseFieldDefinitions
   * @covers ::buildBaseFieldDefinitions
   */
  public function testGetBaseFieldDefinitionsInvalidDefinition() {
    $this->setUpEntityWithFieldDefinition(FALSE, 'langcode', ['langcode' => 'langcode']);

    $this->entityType->isTranslatable()->willReturn(TRUE);
    $this->entityType->getLabel()->willReturn('the_label');

    $this->setExpectedException(\LogicException::class);
    $this->entityFieldManager->getBaseFieldDefinitions('test_entity_type');
  }

  /**
   * Tests that getFieldDefinitions() method sets the 'provider' definition key.
   *
   * @covers ::getFieldDefinitions
   * @covers ::buildBundleFieldDefinitions
   */
  public function testGetFieldDefinitionsProvider() {
    $this->setUpEntityWithFieldDefinition(TRUE);

    $module = 'entity_manager_test_module';

    // @todo Mock FieldDefinitionInterface once it exposes a proper provider
    //   setter. See https://www.drupal.org/node/2225961.
    $field_definition = $this->prophesize(BaseFieldDefinition::class);

    // We expect two calls as the field definition will be returned from both
    // base and bundle entity field info hook implementations.
    $field_definition->getProvider()->shouldBeCalled();
    $field_definition->setProvider($module)->shouldBeCalledTimes(2);
    $field_definition->setName(0)->shouldBeCalledTimes(2);
    $field_definition->setTargetEntityTypeId('test_entity_type')->shouldBeCalled();
    $field_definition->setTargetBundle(NULL)->shouldBeCalled();
    $field_definition->setTargetBundle('test_bundle')->shouldBeCalled();

    $this->moduleHandler->getImplementations(Argument::type('string'))->willReturn([$module]);
    $this->moduleHandler->invoke($module, 'entity_base_field_info', [$this->entityType])->willReturn([$field_definition->reveal()]);
    $this->moduleHandler->invoke($module, 'entity_bundle_field_info', Argument::type('array'))->willReturn([$field_definition->reveal()]);

    $this->entityFieldManager->getFieldDefinitions('test_entity_type', 'test_bundle');
  }

  /**
   * Prepares an entity that defines a field definition.
   *
   * @param bool $custom_invoke_all
   *   (optional) Whether the test will set up its own
   *   ModuleHandlerInterface::invokeAll() implementation. Defaults to FALSE.
   * @param string $field_definition_id
   *   (optional) The ID to use for the field definition. Defaults to 'id'.
   * @param array $entity_keys
   *   (optional) An array of entity keys for the mocked entity type. Defaults
   *   to an empty array.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition|\Prophecy\Prophecy\ProphecyInterface
   *   A field definition object.
   */
  protected function setUpEntityWithFieldDefinition($custom_invoke_all = FALSE, $field_definition_id = 'id', $entity_keys = []) {
    $field_type_manager = $this->prophesize(FieldTypePluginManagerInterface::class);
    $field_type_manager->getDefaultStorageSettings('boolean')->willReturn([]);
    $field_type_manager->getDefaultFieldSettings('boolean')->willReturn([]);
    $this->container->get('plugin.manager.field.field_type')->willReturn($field_type_manager->reveal());

    $string_translation = $this->prophesize(TranslationInterface::class);
    $this->container->get('string_translation')->willReturn($string_translation->reveal());

    $entity_class = EntityManagerTestEntity::class;

    $field_definition = $this->prophesize()->willImplement(FieldDefinitionInterface::class)->willImplement(FieldStorageDefinitionInterface::class);
    $entity_class::$baseFieldDefinitions = [
      $field_definition_id => $field_definition->reveal(),
    ];
    $entity_class::$bundleFieldDefinitions = [];

    if (!$custom_invoke_all) {
      $this->moduleHandler->getImplementations(Argument::cetera())->willReturn([]);
    }

    // Mock the base field definition override.
    $override_entity_type = $this->prophesize(EntityTypeInterface::class);

    $this->entityType = $this->prophesize(EntityTypeInterface::class);
    $this->setUpEntityTypeDefinitions(['test_entity_type' => $this->entityType, 'base_field_override' => $override_entity_type]);

    $storage = $this->prophesize(ConfigEntityStorageInterface::class);
    $storage->loadMultiple(Argument::type('array'))->willReturn([]);
    $this->entityTypeManager->getStorage('base_field_override')->willReturn($storage->reveal());

    $this->entityType->getClass()->willReturn($entity_class);
    $this->entityType->getKeys()->willReturn($entity_keys + ['default_langcode' => 'default_langcode']);
    $this->entityType->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE);
    $this->entityType->isTranslatable()->willReturn(FALSE);
    $this->entityType->isRevisionable()->willReturn(FALSE);
    $this->entityType->getProvider()->willReturn('the_provider');
    $this->entityType->id()->willReturn('the_entity_id');

    return $field_definition->reveal();
  }

  /**
   * Tests the clearCachedFieldDefinitions() method.
   *
   * @covers ::clearCachedFieldDefinitions
   */
  public function testClearCachedFieldDefinitions() {
    $this->setUpEntityTypeDefinitions();

    $this->cacheTagsInvalidator->invalidateTags(['entity_field_info'])->shouldBeCalled();
    $this->container->get('cache_tags.invalidator')->willReturn($this->cacheTagsInvalidator->reveal())->shouldBeCalled();

    $this->typedDataManager->clearCachedDefinitions()->shouldBeCalled();

    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * @covers ::getExtraFields
   */
  public function testGetExtraFields() {
    $this->setUpEntityTypeDefinitions();

    $entity_type_id = $this->randomMachineName();
    $bundle = $this->randomMachineName();
    $language_code = 'en';
    $hook_bundle_extra_fields = [
      $entity_type_id => [
        $bundle => [
          'form' => [
            'foo_extra_field' => [
              'label' => 'Foo',
            ],
          ],
        ],
      ],
    ];
    $processed_hook_bundle_extra_fields = $hook_bundle_extra_fields;
    $processed_hook_bundle_extra_fields[$entity_type_id][$bundle] += [
      'display' => [],
    ];
    $cache_id = 'entity_bundle_extra_fields:' . $entity_type_id . ':' . $bundle . ':' . $language_code;

    $language = new Language(['id' => $language_code]);
    $this->languageManager->getCurrentLanguage()
      ->willReturn($language)
      ->shouldBeCalledTimes(1);

    $this->cacheBackend->get($cache_id)->shouldBeCalled();

    $this->moduleHandler->invokeAll('entity_extra_field_info')->willReturn($hook_bundle_extra_fields);
    $this->moduleHandler->alter('entity_extra_field_info', $hook_bundle_extra_fields)->shouldBeCalled();

    $this->cacheBackend->set($cache_id, $processed_hook_bundle_extra_fields[$entity_type_id][$bundle], Cache::PERMANENT, ['entity_field_info'])->shouldBeCalled();

    $this->assertSame($processed_hook_bundle_extra_fields[$entity_type_id][$bundle], $this->entityFieldManager->getExtraFields($entity_type_id, $bundle));
  }

  /**
   * @covers ::getFieldMap
   */
  public function testGetFieldMap() {
    $this->entityTypeBundleInfo->getBundleInfo('test_entity_type')->willReturn([])->shouldBeCalled();

    // Set up a content entity type.
    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);
    $entity_class = EntityManagerTestEntity::class;

    // Define an ID field definition as a base field.
    $id_definition = $this->prophesize(FieldDefinitionInterface::class);
    $id_definition->getType()->willReturn('integer');
    $base_field_definitions = [
      'id' => $id_definition->reveal(),
    ];
    $entity_class::$baseFieldDefinitions = $base_field_definitions;

    // Set up the stored bundle field map.
    $key_value_store = $this->prophesize(KeyValueStoreInterface::class);
    $this->keyValueFactory->get('entity.definitions.bundle_field_map')->willReturn($key_value_store->reveal());
    $key_value_store->getAll()->willReturn([
      'test_entity_type' => [
        'by_bundle' => [
          'type' => 'string',
          'bundles' => ['second_bundle' => 'second_bundle'],
        ],
      ],
    ]);

    // Set up a non-content entity type.
    $non_content_entity_type = $this->prophesize(EntityTypeInterface::class);

    // Mock the base field definition override.
    $override_entity_type = $this->prophesize(EntityTypeInterface::class);

    $this->setUpEntityTypeDefinitions([
      'test_entity_type' => $entity_type,
      'non_fieldable' => $non_content_entity_type,
      'base_field_override' => $override_entity_type,
    ]);

    $entity_type->getClass()->willReturn($entity_class);
    $entity_type->getKeys()->willReturn(['default_langcode' => 'default_langcode']);
    $entity_type->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE);
    $entity_type->isTranslatable()->shouldBeCalled();
    $entity_type->isRevisionable()->shouldBeCalled();
    $entity_type->getProvider()->shouldBeCalled();

    $non_content_entity_type->entityClassImplements(FieldableEntityInterface::class)->willReturn(FALSE);

    $override_entity_type->entityClassImplements(FieldableEntityInterface::class)->willReturn(FALSE);

    // Set up the entity type bundle info to return two bundles for the
    // fieldable entity type.
    $this->entityTypeBundleInfo->getBundleInfo('test_entity_type')->willReturn([
      'first_bundle' => 'first_bundle',
      'second_bundle' => 'second_bundle',
    ])->shouldBeCalled();
    $this->moduleHandler->getImplementations('entity_base_field_info')->willReturn([]);

    $expected = [
      'test_entity_type' => [
        'id' => [
          'type' => 'integer',
          'bundles' => ['first_bundle' => 'first_bundle', 'second_bundle' => 'second_bundle'],
        ],
        'by_bundle' => [
          'type' => 'string',
          'bundles' => ['second_bundle' => 'second_bundle'],
        ],
      ],
    ];
    $this->assertEquals($expected, $this->entityFieldManager->getFieldMap());
  }

  /**
   * @covers ::getFieldMap
   */
  public function testGetFieldMapFromCache() {
    $expected = [
      'test_entity_type' => [
        'id' => [
          'type' => 'integer',
          'bundles' => ['first_bundle' => 'first_bundle', 'second_bundle' => 'second_bundle'],
        ],
        'by_bundle' => [
          'type' => 'string',
          'bundles' => ['second_bundle' => 'second_bundle'],
        ],
      ],
    ];
    $this->setUpEntityTypeDefinitions();
    $this->cacheBackend->get('entity_field_map')->willReturn((object) ['data' => $expected]);

    // Call the field map twice to make sure the static cache works.
    $this->assertEquals($expected, $this->entityFieldManager->getFieldMap());
    $this->assertEquals($expected, $this->entityFieldManager->getFieldMap());
  }

  /**
   * @covers ::getFieldMapByFieldType
   */
  public function testGetFieldMapByFieldType() {
    // Set up a content entity type.
    $entity_type = $this->prophesize(ContentEntityTypeInterface::class);
    $entity_class = EntityManagerTestEntity::class;

    // Set up the entity type bundle info to return two bundles for the
    // fieldable entity type.
    $this->entityTypeBundleInfo->getBundleInfo('test_entity_type')->willReturn([
      'first_bundle' => 'first_bundle',
      'second_bundle' => 'second_bundle',
    ])->shouldBeCalled();
    $this->moduleHandler->getImplementations('entity_base_field_info')->willReturn([])->shouldBeCalled();

    // Define an ID field definition as a base field.
    $id_definition = $this->prophesize(FieldDefinitionInterface::class);
    $id_definition->getType()->willReturn('integer')->shouldBeCalled();
    $base_field_definitions = [
      'id' => $id_definition->reveal(),
    ];
    $entity_class::$baseFieldDefinitions = $base_field_definitions;

    // Set up the stored bundle field map.
    $key_value_store = $this->prophesize(KeyValueStoreInterface::class);
    $this->keyValueFactory->get('entity.definitions.bundle_field_map')->willReturn($key_value_store->reveal())->shouldBeCalled();
    $key_value_store->getAll()->willReturn([
      'test_entity_type' => [
        'by_bundle' => [
          'type' => 'string',
          'bundles' => ['second_bundle' => 'second_bundle'],
        ],
      ],
    ])->shouldBeCalled();

    // Mock the base field definition override.
    $override_entity_type = $this->prophesize(EntityTypeInterface::class);

    $this->setUpEntityTypeDefinitions([
      'test_entity_type' => $entity_type,
      'base_field_override' => $override_entity_type,
    ]);

    $entity_type->getClass()->willReturn($entity_class)->shouldBeCalled();
    $entity_type->getKeys()->willReturn(['default_langcode' => 'default_langcode'])->shouldBeCalled();
    $entity_type->entityClassImplements(FieldableEntityInterface::class)->willReturn(TRUE)->shouldBeCalled();
    $entity_type->isTranslatable()->shouldBeCalled();
    $entity_type->isRevisionable()->shouldBeCalled();
    $entity_type->getProvider()->shouldBeCalled();

    $override_entity_type->entityClassImplements(FieldableEntityInterface::class)->willReturn(FALSE)->shouldBeCalled();

    $integerFields = $this->entityFieldManager->getFieldMapByFieldType('integer');
    $this->assertCount(1, $integerFields['test_entity_type']);
    $this->assertArrayNotHasKey('non_fieldable', $integerFields);
    $this->assertArrayHasKey('id', $integerFields['test_entity_type']);
    $this->assertArrayNotHasKey('by_bundle', $integerFields['test_entity_type']);

    $stringFields = $this->entityFieldManager->getFieldMapByFieldType('string');
    $this->assertCount(1, $stringFields['test_entity_type']);
    $this->assertArrayNotHasKey('non_fieldable', $stringFields);
    $this->assertArrayHasKey('by_bundle', $stringFields['test_entity_type']);
    $this->assertArrayNotHasKey('id', $stringFields['test_entity_type']);
  }

}

class TestEntityFieldManager extends EntityFieldManager {

  /**
   * Allows the static caches to be cleared.
   */
  public function testClearEntityFieldInfo() {
    $this->baseFieldDefinitions = [];
    $this->fieldDefinitions = [];
    $this->fieldStorageDefinitions = [];
  }

}

/**
 * Provides a content entity with dummy static method implementations.
 */
abstract class EntityManagerTestEntity implements \Iterator, ContentEntityInterface {

  /**
   * The base field definitions.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface[]
   */
  public static $baseFieldDefinitions = [];

  /**
   * The bundle field definitions.
   *
   * @var array[]
   *   Keys are entity type IDs, values are arrays of which the keys are bundle
   *   names and the values are field definitions.
   */
  public static $bundleFieldDefinitions = [];

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    return static::$baseFieldDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    return isset(static::$bundleFieldDefinitions[$entity_type->id()][$bundle]) ? static::$bundleFieldDefinitions[$entity_type->id()][$bundle] : [];
  }

}
