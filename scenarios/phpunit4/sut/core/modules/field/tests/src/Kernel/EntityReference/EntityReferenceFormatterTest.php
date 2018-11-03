<?php

namespace Drupal\Tests\field\Kernel\EntityReference;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\entity_test\Entity\EntityTestLabel;

/**
 * Tests the formatters functionality.
 *
 * @group entity_reference
 */
class EntityReferenceFormatterTest extends EntityKernelTestBase {

  use EntityReferenceTestTrait;

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * The entity to be referenced in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $referencedEntity;

  /**
   * The entity that is not yet saved to its persistent storage to be referenced
   * in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $unsavedReferencedEntity;

  protected function setUp() {
    parent::setUp();

    // Use Classy theme for testing markup output.
    \Drupal::service('theme_handler')->install(['classy']);
    $this->config('system.theme')->set('default', 'classy')->save();
    $this->installEntitySchema('entity_test');
    // Grant the 'view test entity' permission.
    $this->installConfig(['user']);
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('view test entity')
      ->save();

    // The label formatter rendering generates links, so build the router.
    $this->container->get('router.builder')->rebuild();

    $this->createEntityReferenceField($this->entityType, $this->bundle, $this->fieldName, 'Field test', $this->entityType, 'default', [], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Set up a field, so that the entity that'll be referenced bubbles up a
    // cache tag when rendering it entirely.
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => $this->entityType,
      'type' => 'text',
      'settings' => [],
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'field_name' => 'body',
      'label' => 'Body',
    ])->save();
    entity_get_display($this->entityType, $this->bundle, 'default')
      ->setComponent('body', [
        'type' => 'text_default',
        'settings' => [],
      ])
      ->save();

    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ])->save();

    // Create the entity to be referenced.
    $this->referencedEntity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);
    $this->referencedEntity->body = [
      'value' => '<p>Hello, world!</p>',
      'format' => 'full_html',
    ];
    $this->referencedEntity->save();

    // Create another entity to be referenced but do not save it.
    $this->unsavedReferencedEntity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);
    $this->unsavedReferencedEntity->body = [
      'value' => '<p>Hello, unsaved world!</p>',
      'format' => 'full_html',
    ];
  }

  /**
   * Assert inaccessible items don't change the data of the fields.
   */
  public function testAccess() {
    // Revoke the 'view test entity' permission for this test.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->revokePermission('view test entity')
      ->save();

    $field_name = $this->fieldName;

    $referencing_entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);
    $referencing_entity->save();
    $referencing_entity->{$field_name}->entity = $this->referencedEntity;

    // Assert user doesn't have access to the entity.
    $this->assertFalse($this->referencedEntity->access('view'), 'Current user does not have access to view the referenced entity.');

    $formatter_manager = $this->container->get('plugin.manager.field.formatter');

    // Get all the existing formatters.
    foreach ($formatter_manager->getOptions('entity_reference') as $formatter => $name) {
      // Set formatter type for the 'full' view mode.
      entity_get_display($this->entityType, $this->bundle, 'default')
        ->setComponent($field_name, [
          'type' => $formatter,
        ])
        ->save();

      // Invoke entity view.
      entity_view($referencing_entity, 'default');

      // Verify the un-accessible item still exists.
      $this->assertEqual($referencing_entity->{$field_name}->target_id, $this->referencedEntity->id(), format_string('The un-accessible item still exists after @name formatter was executed.', ['@name' => $name]));
    }
  }

  /**
   * Tests the merging of cache metadata.
   */
  public function testCustomCacheTagFormatter() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'entity_reference_custom_cache_tag';
    $build = $this->buildRenderArray([$this->referencedEntity], $formatter);

    $renderer->renderRoot($build);
    $this->assertTrue(in_array('custom_cache_tag', $build['#cache']['tags']));
  }

  /**
   * Tests the ID formatter.
   */
  public function testIdFormatter() {
    $formatter = 'entity_reference_entity_id';
    $build = $this->buildRenderArray([$this->referencedEntity, $this->unsavedReferencedEntity], $formatter);

    $this->assertEqual($build[0]['#plain_text'], $this->referencedEntity->id(), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEqual($build[0]['#cache']['tags'], $this->referencedEntity->getCacheTags(), sprintf('The %s formatter has the expected cache tags.', $formatter));
    $this->assertTrue(!isset($build[1]), sprintf('The markup returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));
  }

  /**
   * Tests the entity formatter.
   */
  public function testEntityFormatter() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'entity_reference_entity_view';
    $build = $this->buildRenderArray([$this->referencedEntity, $this->unsavedReferencedEntity], $formatter);

    // Test the first field item.
    $expected_rendered_name_field_1 = '
            <div class="field field--name-name field--type-string field--label-hidden field__item">' . $this->referencedEntity->label() . '</div>
      ';
    $expected_rendered_body_field_1 = '
  <div class="clearfix text-formatted field field--name-body field--type-text field--label-above">
    <div class="field__label">Body</div>
              <div class="field__item"><p>Hello, world!</p></div>
          </div>
';
    $renderer->renderRoot($build[0]);
    $this->assertEqual($build[0]['#markup'], 'default | ' . $this->referencedEntity->label() . $expected_rendered_name_field_1 . $expected_rendered_body_field_1, sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $expected_cache_tags = Cache::mergeTags(\Drupal::entityManager()->getViewBuilder($this->entityType)->getCacheTags(), $this->referencedEntity->getCacheTags());
    $expected_cache_tags = Cache::mergeTags($expected_cache_tags, FilterFormat::load('full_html')->getCacheTags());
    $this->assertEqual($build[0]['#cache']['tags'], $expected_cache_tags, format_string('The @formatter formatter has the expected cache tags.', ['@formatter' => $formatter]));

    // Test the second field item.
    $expected_rendered_name_field_2 = '
            <div class="field field--name-name field--type-string field--label-hidden field__item">' . $this->unsavedReferencedEntity->label() . '</div>
      ';
    $expected_rendered_body_field_2 = '
  <div class="clearfix text-formatted field field--name-body field--type-text field--label-above">
    <div class="field__label">Body</div>
              <div class="field__item"><p>Hello, unsaved world!</p></div>
          </div>
';

    $renderer->renderRoot($build[1]);
    $this->assertEqual($build[1]['#markup'], 'default | ' . $this->unsavedReferencedEntity->label() . $expected_rendered_name_field_2 . $expected_rendered_body_field_2, sprintf('The markup returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));
  }

  /**
   * Tests the recursive rendering protection of the entity formatter.
   */
  public function testEntityFormatterRecursiveRendering() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'entity_reference_entity_view';
    $view_builder = $this->entityManager->getViewBuilder($this->entityType);

    // Set the default view mode to use the 'entity_reference_entity_view'
    // formatter.
    entity_get_display($this->entityType, $this->bundle, 'default')
      ->setComponent($this->fieldName, [
        'type' => $formatter,
      ])
      ->save();

    $referencing_entity_1 = entity_create($this->entityType, ['name' => $this->randomMachineName()]);
    $referencing_entity_1->save();

    // Create a self-reference.
    $referencing_entity_1->{$this->fieldName}->entity = $referencing_entity_1;
    $referencing_entity_1->save();

    // Check that the recursive rendering stops after it reaches the specified
    // limit.
    $build = $view_builder->view($referencing_entity_1, 'default');
    $output = $renderer->renderRoot($build);

    // The title of entity_test entities is printed twice by default, so we have
    // to multiply the formatter's recursive rendering protection limit by 2.
    // Additionally, we have to take into account 2 additional occurrences of
    // the entity title because we're rendering the full entity, not just the
    // reference field.
    $expected_occurrences = EntityReferenceEntityFormatter::RECURSIVE_RENDER_LIMIT * 2 + 2;
    $actual_occurrences = substr_count($output, $referencing_entity_1->name->value);
    $this->assertEqual($actual_occurrences, $expected_occurrences);

    // Repeat the process with another entity in order to check that the
    // 'recursive_render_id' counter is generated properly.
    $referencing_entity_2 = entity_create($this->entityType, ['name' => $this->randomMachineName()]);
    $referencing_entity_2->save();
    $referencing_entity_2->{$this->fieldName}->entity = $referencing_entity_2;
    $referencing_entity_2->save();

    $build = $view_builder->view($referencing_entity_2, 'default');
    $output = $renderer->renderRoot($build);

    $actual_occurrences = substr_count($output, $referencing_entity_2->name->value);
    $this->assertEqual($actual_occurrences, $expected_occurrences);

    // Now render both entities at the same time and check again.
    $build = $view_builder->viewMultiple([$referencing_entity_1, $referencing_entity_2], 'default');
    $output = $renderer->renderRoot($build);

    $actual_occurrences = substr_count($output, $referencing_entity_1->name->value);
    $this->assertEqual($actual_occurrences, $expected_occurrences);

    $actual_occurrences = substr_count($output, $referencing_entity_2->name->value);
    $this->assertEqual($actual_occurrences, $expected_occurrences);
  }

  /**
   * Renders the same entity referenced from different places.
   */
  public function testEntityReferenceRecursiveProtectionWithManyRenderedEntities() {
    $formatter = 'entity_reference_entity_view';
    $view_builder = $this->entityManager->getViewBuilder($this->entityType);

    // Set the default view mode to use the 'entity_reference_entity_view'
    // formatter.
    entity_get_display($this->entityType, $this->bundle, 'default')
      ->setComponent($this->fieldName, [
        'type' => $formatter,
      ])
      ->save();

    $storage = $this->entityManager->getStorage($this->entityType);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
    $referenced_entity = $storage->create(['name' => $this->randomMachineName()]);

    $range = range(0, 30);
    $referencing_entities = array_map(function () use ($storage, $referenced_entity) {
      $referencing_entity = $storage->create([
        'name' => $this->randomMachineName(),
        $this->fieldName => $referenced_entity,
      ]);
      $referencing_entity->save();
      return $referencing_entity;
    }, $range);

    $build = $view_builder->viewMultiple($referencing_entities, 'default');
    $output = $this->render($build);

    // The title of entity_test entities is printed twice by default, so we have
    // to multiply the formatter's recursive rendering protection limit by 2.
    // Additionally, we have to take into account 2 additional occurrences of
    // the entity title because we're rendering the full entity, not just the
    // reference field.
    $expected_occurrences = 30 * 2 + 2;
    $actual_occurrences = substr_count($output, $referenced_entity->get('name')->value);
    $this->assertEquals($expected_occurrences, $actual_occurrences);
  }

  /**
   * Tests the label formatter.
   */
  public function testLabelFormatter() {
    $this->installEntitySchema('entity_test_label');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'entity_reference_label';

    // The 'link' settings is TRUE by default.
    $build = $this->buildRenderArray([$this->referencedEntity, $this->unsavedReferencedEntity], $formatter);

    $expected_field_cacheability = [
      'contexts' => [],
      'tags' => [],
      'max-age' => Cache::PERMANENT,
    ];
    $this->assertEqual($build['#cache'], $expected_field_cacheability, 'The field render array contains the entity access cacheability metadata');
    $expected_item_1 = [
      '#type' => 'link',
      '#title' => $this->referencedEntity->label(),
      '#url' => $this->referencedEntity->urlInfo(),
      '#options' => $this->referencedEntity->urlInfo()->getOptions(),
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
        'tags' => $this->referencedEntity->getCacheTags(),
      ],
    ];
    $this->assertEqual($renderer->renderRoot($build[0]), $renderer->renderRoot($expected_item_1), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEqual(CacheableMetadata::createFromRenderArray($build[0]), CacheableMetadata::createFromRenderArray($expected_item_1));

    // The second referenced entity is "autocreated", therefore not saved and
    // lacking any URL info.
    $expected_item_2 = [
      '#plain_text' => $this->unsavedReferencedEntity->label(),
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
        'tags' => $this->unsavedReferencedEntity->getCacheTags(),
        'max-age' => Cache::PERMANENT,
      ],
    ];
    $this->assertEqual($build[1], $expected_item_2, sprintf('The render array returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));

    // Test with the 'link' setting set to FALSE.
    $build = $this->buildRenderArray([$this->referencedEntity, $this->unsavedReferencedEntity], $formatter, ['link' => FALSE]);
    $this->assertEqual($build[0]['#plain_text'], $this->referencedEntity->label(), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEqual($build[1]['#plain_text'], $this->unsavedReferencedEntity->label(), sprintf('The markup returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));

    // Test an entity type that doesn't have any link templates, which means
    // \Drupal\Core\Entity\EntityInterface::urlInfo() will throw an exception
    // and the label formatter will output only the label instead of a link.
    $field_storage_config = FieldStorageConfig::loadByName($this->entityType, $this->fieldName);
    $field_storage_config->setSetting('target_type', 'entity_test_label');
    $field_storage_config->save();

    $referenced_entity_with_no_link_template = EntityTestLabel::create([
      'name' => $this->randomMachineName(),
    ]);
    $referenced_entity_with_no_link_template->save();

    $build = $this->buildRenderArray([$referenced_entity_with_no_link_template], $formatter, ['link' => TRUE]);
    $this->assertEqual($build[0]['#plain_text'], $referenced_entity_with_no_link_template->label(), sprintf('The markup returned by the %s formatter is correct for an entity type with no valid link template.', $formatter));
  }

  /**
   * Sets field values and returns a render array as built by
   * \Drupal\Core\Field\FieldItemListInterface::view().
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $referenced_entities
   *   An array of entity objects that will be referenced.
   * @param string $formatter
   *   The formatted plugin that will be used for building the render array.
   * @param array $formatter_options
   *   Settings specific to the formatter. Defaults to the formatter's default
   *   settings.
   *
   * @return array
   *   A render array.
   */
  protected function buildRenderArray(array $referenced_entities, $formatter, $formatter_options = []) {
    // Create the entity that will have the entity reference field.
    $referencing_entity = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->create(['name' => $this->randomMachineName()]);

    $items = $referencing_entity->get($this->fieldName);

    // Assign the referenced entities.
    foreach ($referenced_entities as $referenced_entity) {
      $items[] = ['entity' => $referenced_entity];
    }

    // Build the renderable array for the field.
    return $items->view(['type' => $formatter, 'settings' => $formatter_options]);
  }

}
