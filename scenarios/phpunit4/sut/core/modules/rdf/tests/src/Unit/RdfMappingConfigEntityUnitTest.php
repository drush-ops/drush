<?php

namespace Drupal\Tests\rdf\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\rdf\Entity\RdfMapping;

/**
 * @coversDefaultClass \Drupal\rdf\Entity\RdfMapping
 * @group rdf
 */
class RdfMappingConfigEntityUnitTest extends UnitTestCase {

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
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->entityTypeId = $this->randomMachineName();

    $this->entityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->entityType->expects($this->any())
      ->method('getProvider')
      ->will($this->returnValue('entity'));

    $this->entityManager = new EntityManager();
    $this->entityTypeManager = $this->getMock(EntityTypeManagerInterface::class);

    $this->uuid = $this->getMock('\Drupal\Component\Uuid\UuidInterface');

    $container = new ContainerBuilder();
    $container->set('entity.manager', $this->entityManager);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);
    $this->entityManager->setContainer($container);
    \Drupal::setContainer($container);

  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependencies() {
    $target_entity_type_id = $this->randomMachineName(16);

    $target_entity_type = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $target_entity_type->expects($this->any())
      ->method('getProvider')
      ->will($this->returnValue('test_module'));
    $values = ['targetEntityType' => $target_entity_type_id];
    $target_entity_type->expects($this->any())
      ->method('getBundleEntityType')
      ->will($this->returnValue(NULL));

    $this->entityTypeManager->expects($this->at(0))
      ->method('getDefinition')
      ->with($target_entity_type_id)
      ->will($this->returnValue($target_entity_type));
    $this->entityTypeManager->expects($this->at(1))
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->will($this->returnValue($this->entityType));

    $entity = new RdfMapping($values, $this->entityTypeId);
    $dependencies = $entity->calculateDependencies()->getDependencies();
    $this->assertArrayNotHasKey('config', $dependencies);
    $this->assertContains('test_module', $dependencies['module']);
  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependenciesWithEntityBundle() {
    $target_entity_type_id = $this->randomMachineName(16);
    $target_entity_type = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $target_entity_type->expects($this->any())
      ->method('getProvider')
      ->will($this->returnValue('test_module'));
    $bundle_id = $this->randomMachineName(10);
    $values = ['targetEntityType' => $target_entity_type_id , 'bundle' => $bundle_id];

    $target_entity_type->expects($this->any())
      ->method('getBundleConfigDependency')
      ->will($this->returnValue(['type' => 'config', 'name' => 'test_module.type.' . $bundle_id]));

    $this->entityTypeManager->expects($this->at(0))
      ->method('getDefinition')
      ->with($target_entity_type_id)
      ->will($this->returnValue($target_entity_type));
    $this->entityTypeManager->expects($this->at(1))
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->will($this->returnValue($this->entityType));

    $entity = new RdfMapping($values, $this->entityTypeId);
    $dependencies = $entity->calculateDependencies()->getDependencies();
    $this->assertContains('test_module.type.' . $bundle_id, $dependencies['config']);
    $this->assertContains('test_module', $dependencies['module']);
  }

}
