<?php

namespace Drupal\Tests\content_moderation\Unit;

use Drupal\content_moderation\Entity\Handler\ModerationHandler;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformation;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\WorkflowInterface;

/**
 * @coversDefaultClass \Drupal\content_moderation\ModerationInformation
 * @group content_moderation
 */
class ModerationInformationTest extends UnitTestCase {

  /**
   * Builds a mock user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The mocked user.
   */
  protected function getUser() {
    return $this->prophesize(AccountInterface::class)->reveal();
  }

  /**
   * Returns a mock Entity Type Manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The mocked entity type manager.
   */
  protected function getEntityTypeManager() {
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    return $entity_type_manager->reveal();
  }

  /**
   * Sets up content moderation and entity manager mocking.
   *
   * @param string $bundle
   *   The bundle ID.
   * @param string|null $workflow
   *   The workflow ID. If nul no workflow information is added to the bundle.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The mocked entity type manager.
   */
  public function setupModerationBundleInfo($bundle, $workflow = NULL) {
    $bundle_info_array = [];
    if ($workflow) {
      $bundle_info_array['workflow'] = $workflow;
    }
    $bundle_info = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $bundle_info->getBundleInfo("test_entity_type")->willReturn([$bundle => $bundle_info_array]);

    return $bundle_info->reveal();
  }

  /**
   * @dataProvider providerWorkflow
   * @covers ::isModeratedEntity
   */
  public function testIsModeratedEntity($workflow, $expected) {
    $moderation_information = new ModerationInformation($this->getEntityTypeManager(), $this->setupModerationBundleInfo('test_bundle', $workflow));

    $entity_type = new ContentEntityType([
      'id' => 'test_entity_type',
      'bundle_entity_type' => 'entity_test_bundle',
      'handlers' => ['moderation' => ModerationHandler::class],
    ]);
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->getEntityType()->willReturn($entity_type);
    $entity->bundle()->willReturn('test_bundle');

    $this->assertEquals($expected, $moderation_information->isModeratedEntity($entity->reveal()));
  }

  /**
   * @dataProvider providerWorkflow
   * @covers ::getWorkflowForEntity
   */
  public function testGetWorkflowForEntity($workflow) {
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    if ($workflow) {
      $workflow_entity = $this->prophesize(WorkflowInterface::class)->reveal();
      $workflow_storage = $this->prophesize(EntityStorageInterface::class);
      $workflow_storage->load('workflow')->willReturn($workflow_entity)->shouldBeCalled();
      $entity_type_manager->getStorage('workflow')->willReturn($workflow_storage->reveal());
    }
    else {
      $workflow_entity = NULL;
    }
    $moderation_information = new ModerationInformation($entity_type_manager->reveal(), $this->setupModerationBundleInfo('test_bundle', $workflow));
    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->getEntityTypeId()->willReturn('test_entity_type');
    $entity->bundle()->willReturn('test_bundle');

    $this->assertEquals($workflow_entity, $moderation_information->getWorkflowForEntity($entity->reveal()));
  }

  /**
   * @dataProvider providerWorkflow
   * @covers ::shouldModerateEntitiesOfBundle
   */
  public function testShouldModerateEntities($workflow, $expected) {
    $entity_type = new ContentEntityType([
      'id' => 'test_entity_type',
      'bundle_entity_type' => 'entity_test_bundle',
      'handlers' => ['moderation' => ModerationHandler::class],
    ]);

    $moderation_information = new ModerationInformation($this->getEntityTypeManager(), $this->setupModerationBundleInfo('test_bundle', $workflow));

    $this->assertEquals($expected, $moderation_information->shouldModerateEntitiesOfBundle($entity_type, 'test_bundle'));
  }

  /**
   * Data provider for several tests.
   */
  public function providerWorkflow() {
    return [
      [NULL, FALSE],
      ['workflow', TRUE],
    ];
  }

}
