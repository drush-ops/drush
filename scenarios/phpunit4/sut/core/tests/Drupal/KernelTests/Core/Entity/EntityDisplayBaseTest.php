<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\comment\Entity\CommentType;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Entity\EntityDisplayBase
 *
 * @group Entity
 */
class EntityDisplayBaseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test', 'entity_test_third_party', 'field', 'system', 'comment'];

  /**
   * @covers ::preSave
   */
  public function testPreSave() {
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'foo' => ['type' => 'visible'],
        'bar' => ['type' => 'hidden'],
        'name' => ['type' => 'hidden', 'region' => 'content'],
      ],
    ]);

    // Ensure that no region is set on the component.
    $this->assertArrayNotHasKey('region', $entity_display->getComponent('foo'));
    $this->assertArrayNotHasKey('region', $entity_display->getComponent('bar'));

    // Ensure that a region is set on the component after saving.
    $entity_display->save();

    // The component with a visible type has been assigned a region.
    $component = $entity_display->getComponent('foo');
    $this->assertArrayHasKey('region', $component);
    $this->assertSame('content', $component['region']);

    // The component with a hidden type has been removed.
    $this->assertNull($entity_display->getComponent('bar'));

    // The component with a valid region and hidden type is unchanged.
    $component = $entity_display->getComponent('name');
    $this->assertArrayHasKey('region', $component);
    $this->assertSame('content', $component['region']);
  }

  /**
   * @covers ::onDependencyRemoval
   */
  public function testOnDependencyRemoval() {
    // Create a comment field for entity_test.
    $comment_bundle = CommentType::create([
      'id' => 'entity_test',
      'label' => 'entity_test',
      'description' => '',
      'target_entity_type_id' => 'entity_test',
    ]);
    $comment_bundle->save();
    $comment_display = EntityViewDisplay::create([
      'targetEntityType' => 'comment',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
      'third_party_settings' => [
        'entity_test_third_party' => [
          'key' => 'value',
        ],
      ],
    ]);
    $comment_display->save();
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'test_field',
      'type' => 'comment',
      'settings' => [
        'comment_type' => 'entity_test',
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'label' => $this->randomMachineName(),
      'bundle' => 'entity_test',
    ]);
    $field->save();

    // Create an entity view display for entity_test.
    $entity_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'test_field' => ['type' => 'comment_default', 'region' => 'content', 'settings' => ['view_mode' => 'default'], 'label' => 'hidden', 'third_party_settings' => []],
      ],
      'third_party_settings' => [
        'entity_test_third_party' => [
          'key' => 'value',
        ],
      ],
    ]);
    $entity_display->save();

    $expected_component = [
      'type' => 'comment_default',
      'region' => 'content',
      'settings' => ['view_mode' => 'default'],
      'label' => 'hidden',
      'third_party_settings' => [],
    ];
    $entity_display->getComponent('test_field');
    $this->assertEquals($expected_component, $entity_display->getComponent('test_field'));
    $expected_dependencies = [
      'config' => [
        'core.entity_view_display.comment.entity_test.default',
        'field.field.entity_test.entity_test.test_field',
      ],
      'module' => [
        'comment',
        'entity_test',
        'entity_test_third_party',
      ],
    ];
    $this->assertSame($expected_dependencies, $entity_display->getDependencies());

    // Uninstall the third-party settings provider and reload the display.
    $this->container->get('module_installer')->uninstall(['entity_test_third_party']);
    $entity_display = EntityViewDisplay::load('entity_test.entity_test.default');

    // The component should remain unchanged.
    $this->assertEquals($expected_component, $entity_display->getComponent('test_field'));
    // The dependencies should no longer contain 'entity_test_third_party'.
    $expected_dependencies['module'] = [
      'comment',
      'entity_test',
    ];
    $this->assertSame($expected_dependencies, $entity_display->getDependencies());
  }

}
