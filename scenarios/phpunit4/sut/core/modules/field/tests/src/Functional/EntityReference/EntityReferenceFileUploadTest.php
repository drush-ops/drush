<?php

namespace Drupal\Tests\field\Functional\EntityReference;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests an autocomplete widget with file upload.
 *
 * @group entity_reference
 */
class EntityReferenceFileUploadTest extends BrowserTestBase {

  use TestFileCreationTrait;

  public static $modules = ['entity_reference', 'node', 'file'];

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

  /**
   * Node id.
   *
   * @var int
   */
  protected $nodeId;

  protected function setUp() {
    parent::setUp();

    // Create "referencing" and "referenced" node types.
    $referencing = $this->drupalCreateContentType();
    $this->referencingType = $referencing->id();

    $referenced = $this->drupalCreateContentType();
    $this->referencedType = $referenced->id();
    $this->nodeId = $this->drupalCreateNode(['type' => $referenced->id()])->id();

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
      'required' => TRUE,
      'bundle' => $referencing->id(),
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          // Reference a single vocabulary.
          'target_bundles' => [
            $referenced->id(),
          ],
        ],
      ],
    ])->save();

    // Create a file field.
    $file_field_name = 'file_field';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $file_field_name,
      'entity_type' => 'node',
      'type' => 'file',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_storage' => $field_storage,
      'bundle' => $referencing->id(),
      'label' => $this->randomMachineName() . '_label',
    ])->save();

    entity_get_display('node', $referencing->id(), 'default')
      ->setComponent('test_field')
      ->setComponent($file_field_name)
      ->save();
    entity_get_form_display('node', $referencing->id(), 'default')
      ->setComponent('test_field', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->setComponent($file_field_name, [
         'type' => 'file_generic',
      ])
      ->save();
  }

  /**
   * Tests that the autocomplete input element does not cause ajax fatal.
   */
  public function testFileUpload() {
    $user1 = $this->drupalCreateUser(['access content', "create $this->referencingType content"]);
    $this->drupalLogin($user1);

    $test_file = current($this->getTestFiles('text'));
    $edit['files[file_field_0]'] = \Drupal::service('file_system')->realpath($test_file->uri);
    $this->drupalPostForm('node/add/' . $this->referencingType, $edit, 'Upload');
    $this->assertResponse(200);
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'test_field[0][target_id]' => $this->nodeId,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertResponse(200);
  }

}
