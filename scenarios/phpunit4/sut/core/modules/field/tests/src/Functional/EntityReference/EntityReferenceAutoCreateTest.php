<?php

namespace Drupal\Tests\field\Functional\EntityReference;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\Node;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests creating new entity (e.g. taxonomy-term) from an autocomplete widget.
 *
 * @group entity_reference
 */
class EntityReferenceAutoCreateTest extends BrowserTestBase {

  use EntityReferenceTestTrait;

  public static $modules = ['node', 'taxonomy'];

  /**
   * The name of a content type that will reference $referencedType.
   *
   * @var string
   */
  protected $referencingType;

  /**
   * The name of a content type that will be referenced by $referencingType.
   *
   * @var string
   */
  protected $referencedType;

  protected function setUp() {
    parent::setUp();

    // Create "referencing" and "referenced" node types.
    $referencing = $this->drupalCreateContentType();
    $this->referencingType = $referencing->id();

    $referenced = $this->drupalCreateContentType();
    $this->referencedType = $referenced->id();

    FieldStorageConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'node',
      'translatable' => FALSE,
      'entity_types' => [],
      'settings' => [
        'target_type' => 'node',
      ],
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'label' => 'Entity reference field',
      'field_name' => 'test_field',
      'entity_type' => 'node',
      'bundle' => $referencing->id(),
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          // Reference a single vocabulary.
          'target_bundles' => [
            $referenced->id(),
          ],
          // Enable auto-create.
          'auto_create' => TRUE,
        ],
      ],
    ])->save();

    entity_get_display('node', $referencing->id(), 'default')
      ->setComponent('test_field')
      ->save();
    entity_get_form_display('node', $referencing->id(), 'default')
      ->setComponent('test_field', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();

    $account = $this->drupalCreateUser(['access content', "create $this->referencingType content"]);
    $this->drupalLogin($account);
  }

  /**
   * Tests that the autocomplete input element appears and the creation of a new
   * entity.
   */
  public function testAutoCreate() {
    $this->drupalGet('node/add/' . $this->referencingType);
    $this->assertFieldByXPath('//input[@id="edit-test-field-0-target-id" and contains(@class, "form-autocomplete")]', NULL, 'The autocomplete input element appears.');

    $new_title = $this->randomMachineName();

    // Assert referenced node does not exist.
    $base_query = \Drupal::entityQuery('node');
    $base_query
      ->condition('type', $this->referencedType)
      ->condition('title', $new_title);

    $query = clone $base_query;
    $result = $query->execute();
    $this->assertFalse($result, 'Referenced node does not exist yet.');

    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'test_field[0][target_id]' => $new_title,
    ];
    $this->drupalPostForm("node/add/$this->referencingType", $edit, 'Save');

    // Assert referenced node was created.
    $query = clone $base_query;
    $result = $query->execute();
    $this->assertTrue($result, 'Referenced node was created.');
    $referenced_nid = key($result);
    $referenced_node = Node::load($referenced_nid);

    // Assert the referenced node is associated with referencing node.
    $result = \Drupal::entityQuery('node')
      ->condition('type', $this->referencingType)
      ->execute();

    $referencing_nid = key($result);
    $referencing_node = Node::load($referencing_nid);
    $this->assertEqual($referenced_nid, $referencing_node->test_field->target_id, 'Newly created node is referenced from the referencing node.');

    // Now try to view the node and check that the referenced node is shown.
    $this->drupalGet('node/' . $referencing_node->id());
    $this->assertText($referencing_node->label(), 'Referencing node label found.');
    $this->assertText($referenced_node->label(), 'Referenced node label found.');
  }

  /**
   * Tests if an entity reference field having multiple target bundles is
   * storing the auto-created entity in the right destination.
   */
  public function testMultipleTargetBundles() {
    /** @var \Drupal\taxonomy\Entity\Vocabulary[] $vocabularies */
    $vocabularies = [];
    for ($i = 0; $i < 2; $i++) {
      $vid = mb_strtolower($this->randomMachineName());
      $vocabularies[$i] = Vocabulary::create([
        'name' => $this->randomMachineName(),
        'vid' => $vid,
      ]);
      $vocabularies[$i]->save();
    }

    // Create a taxonomy term entity reference field that saves the auto-created
    // taxonomy terms in the second vocabulary from the two that were configured
    // as targets.
    $field_name = mb_strtolower($this->randomMachineName());
    $handler_settings = [
      'target_bundles' => [
        $vocabularies[0]->id() => $vocabularies[0]->id(),
        $vocabularies[1]->id() => $vocabularies[1]->id(),
      ],
      'auto_create' => TRUE,
      'auto_create_bundle' => $vocabularies[1]->id(),
    ];
    $this->createEntityReferenceField('node', $this->referencingType, $field_name, $this->randomString(), 'taxonomy_term', 'default', $handler_settings);
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $fd */
    entity_get_form_display('node', $this->referencingType, 'default')
      ->setComponent($field_name, ['type' => 'entity_reference_autocomplete'])
      ->save();

    $term_name = $this->randomString();
    $edit = [
      $field_name . '[0][target_id]' => $term_name,
      'title[0][value]' => $this->randomString(),
    ];

    $this->drupalPostForm('node/add/' . $this->referencingType, $edit, 'Save');
    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = taxonomy_term_load_multiple_by_name($term_name);
    $term = reset($term);

    // The new term is expected to be stored in the second vocabulary.
    $this->assertEqual($vocabularies[1]->id(), $term->bundle());

    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = FieldConfig::loadByName('node', $this->referencingType, $field_name);
    $handler_settings = $field_config->getSetting('handler_settings');

    // Change the field setting to store the auto-created terms in the first
    // vocabulary and test again.
    $handler_settings['auto_create_bundle'] = $vocabularies[0]->id();
    $field_config->setSetting('handler_settings', $handler_settings);
    $field_config->save();

    $term_name = $this->randomString();
    $edit = [
      $field_name . '[0][target_id]' => $term_name,
      'title[0][value]' => $this->randomString(),
    ];

    $this->drupalPostForm('node/add/' . $this->referencingType, $edit, 'Save');
    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = taxonomy_term_load_multiple_by_name($term_name);
    $term = reset($term);

    // The second term is expected to be stored in the first vocabulary.
    $this->assertEqual($vocabularies[0]->id(), $term->bundle());

    // @todo Re-enable this test when WebTestBase::curlHeaderCallback() provides
    //   a way to catch and assert user-triggered errors.

    // Test the case when the field config settings are inconsistent.
    // unset($handler_settings['auto_create_bundle']);
    // $field_config->setSetting('handler_settings', $handler_settings);
    // $field_config->save();
    //
    // $this->drupalGet('node/add/' . $this->referencingType);
    // $error_message = sprintf(
    //  "Create referenced entities if they don't already exist option is enabled but a specific destination bundle is not set. You should re-visit and fix the settings of the '%s' (%s) field.",
    //  $field_config->getLabel(),
    //  $field_config->getName()
    // );
    // $this->assertErrorLogged($error_message);
  }

}
