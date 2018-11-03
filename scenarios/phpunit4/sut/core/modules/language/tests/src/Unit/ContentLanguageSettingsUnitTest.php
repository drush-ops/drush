<?php

namespace Drupal\Tests\language\Unit;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\language\Entity\ContentLanguageSettings
 * @group language
 */
class ContentLanguageSettingsUnitTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * The entity manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The UUID generator used for testing.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $uuid;

  /**
   * The typed configuration manager used for testing.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $typedConfigManager;

  /**
   * The typed configuration manager used for testing.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configEntityStorageInterface;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->entityTypeId = $this->randomMachineName();
    $this->entityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');

    $this->entityManager = new EntityManager();
    $this->entityTypeManager = $this->getMock(EntityTypeManagerInterface::class);

    $this->uuid = $this->getMock('\Drupal\Component\Uuid\UuidInterface');

    $this->typedConfigManager = $this->getMock('Drupal\Core\Config\TypedConfigManagerInterface');

    $this->configEntityStorageInterface = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');

    $container = new ContainerBuilder();
    $container->set('entity.manager', $this->entityManager);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);
    $container->set('config.typed', $this->typedConfigManager);
    $container->set('config.storage', $this->configEntityStorageInterface);
    // Inject the container into entity.manager so it can defer to other entity
    // services.
    $this->entityManager->setContainer($container);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependencies() {
    // Mock the interfaces necessary to create a dependency on a bundle entity.
    $target_entity_type = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $target_entity_type->expects($this->any())
      ->method('getBundleConfigDependency')
      ->will($this->returnValue(['type' => 'config', 'name' => 'test.test_entity_type.id']));

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('test_entity_type')
      ->will($this->returnValue($target_entity_type));

    $config = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $dependencies = $config->calculateDependencies()->getDependencies();
    $this->assertContains('test.test_entity_type.id', $dependencies['config']);
  }

  /**
   * @covers ::id
   */
  public function testId() {
    $config = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $this->assertSame('test_entity_type.test_bundle', $config->id());
  }

  /**
   * @covers ::getTargetEntityTypeId
   */
  public function testTargetEntityTypeId() {
    $config = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $this->assertSame('test_entity_type', $config->getTargetEntityTypeId());
  }

  /**
   * @covers ::getTargetBundle
   */
  public function testTargetBundle() {
    $config = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $this->assertSame('test_bundle', $config->getTargetBundle());
  }

  /**
   * @covers ::getDefaultLangcode
   * @covers ::setDefaultLangcode
   *
   * @dataProvider providerDefaultLangcode
   */
  public function testDefaultLangcode(ContentLanguageSettings $config, $expected) {
    $this->assertSame($expected, $config->getDefaultLangcode());
  }

  public function providerDefaultLangcode() {
    $langcode = $this->randomMachineName();
    $config = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $config->setDefaultLangcode($langcode);

    $defaultConfig = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_default_language_bundle',
    ], 'language_content_settings');

    return [
      [$config, $langcode],
      [$defaultConfig, LanguageInterface::LANGCODE_SITE_DEFAULT],
    ];
  }

  /**
   * @covers ::setLanguageAlterable
   * @covers ::isLanguageAlterable
   *
   * @dataProvider providerLanguageAlterable
   */
  public function testLanguageAlterable(ContentLanguageSettings $config, $expected) {
    $this->assertSame($expected, $config->isLanguageAlterable());
  }

  public function providerLanguageAlterable() {
    $alterableConfig = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $alterableConfig->setLanguageAlterable(TRUE);

    $nonAlterableConfig = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_fixed_language_bundle',
    ], 'language_content_settings');
    $nonAlterableConfig->setLanguageAlterable(FALSE);

    $defaultConfig = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_default_language_bundle',
    ], 'language_content_settings');

    return [
      [$alterableConfig, TRUE],
      [$nonAlterableConfig, FALSE],
      [$defaultConfig, FALSE],
    ];
  }

  /**
   * @covers ::isDefaultConfiguration
   *
   * @dataProvider providerIsDefaultConfiguration
   */
  public function testIsDefaultConfiguration(ContentLanguageSettings $config, $expected) {
    $this->assertSame($expected, $config->isDefaultConfiguration());
  }

  public function providerIsDefaultConfiguration() {
    $alteredLanguage = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $alteredLanguage->setLanguageAlterable(TRUE);

    $alteredDefaultLangcode = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_fixed_language_bundle',
    ], 'language_content_settings');
    $alteredDefaultLangcode->setDefaultLangcode($this->randomMachineName());

    $defaultConfig = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_default_language_bundle',
    ], 'language_content_settings');

    return [
      [$alteredLanguage, FALSE],
      [$alteredDefaultLangcode, FALSE],
      [$defaultConfig, TRUE],
    ];
  }

  /**
   * @covers ::loadByEntityTypeBundle
   *
   * @dataProvider providerLoadByEntityTypeBundle
   */
  public function testLoadByEntityTypeBundle($config_id, ContentLanguageSettings $existing_config = NULL, $expected_langcode, $expected_language_alterable) {
    list($type, $bundle) = explode('.', $config_id);

    $nullConfig = new ContentLanguageSettings([
      'target_entity_type_id' => $type,
      'target_bundle' => $bundle,
    ], 'language_content_settings');
    $this->configEntityStorageInterface
      ->expects($this->any())
      ->method('load')
      ->with($config_id)
      ->will($this->returnValue($existing_config));
    $this->configEntityStorageInterface
      ->expects($this->any())
      ->method('create')
      ->will($this->returnValue($nullConfig));

    $this->entityTypeManager
      ->expects($this->any())
      ->method('getStorage')
      ->with('language_content_settings')
      ->will($this->returnValue($this->configEntityStorageInterface));

    $entity_type_repository = $this->getMockForAbstractClass(EntityTypeRepositoryInterface::class);
    $entity_type_repository->expects($this->any())
      ->method('getEntityTypeFromClass')
      ->with(ContentLanguageSettings::class)
      ->willReturn('language_content_settings');

    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository);

    $config = ContentLanguageSettings::loadByEntityTypeBundle($type, $bundle);

    $this->assertSame($expected_langcode, $config->getDefaultLangcode());
    $this->assertSame($expected_language_alterable, $config->isLanguageAlterable());
  }

  public function providerLoadByEntityTypeBundle() {
    $alteredLanguage = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
    ], 'language_content_settings');
    $alteredLanguage->setLanguageAlterable(TRUE);

    $langcode = $this->randomMachineName();
    $alteredDefaultLangcode = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_fixed_language_bundle',
    ], 'language_content_settings');
    $alteredDefaultLangcode->setDefaultLangcode($langcode);

    $defaultConfig = new ContentLanguageSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_default_language_bundle',
    ], 'language_content_settings');

    return [
      ['test_entity_type.test_bundle', $alteredLanguage, LanguageInterface::LANGCODE_SITE_DEFAULT, TRUE],
      ['test_entity_type.test_fixed_language_bundle', $alteredDefaultLangcode, $langcode, FALSE],
      ['test_entity_type.test_default_language_bundle', $defaultConfig, LanguageInterface::LANGCODE_SITE_DEFAULT, FALSE],
      ['test_entity_type.null_bundle', NULL, LanguageInterface::LANGCODE_SITE_DEFAULT, FALSE],
    ];
  }

}
