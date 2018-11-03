<?php

namespace Drupal\field_ui\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simpletest\WebTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests deletion of a field and their dependencies in the UI.
 *
 * @group field_ui
 */
class FieldUIDeleteTest extends WebTestBase {

  use FieldUiTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'field_ui', 'field_test', 'block', 'field_test_views'];

  /**
   * Test views to enable
   *
   * @var string[]
   */
  public static $testViews = ['test_view_field_delete'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create a test user.
    $admin_user = $this->drupalCreateUser(['access content', 'administer content types', 'administer node fields', 'administer node form display', 'administer node display', 'administer users', 'administer account settings', 'administer user display', 'bypass node access']);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that deletion removes field storages and fields as expected.
   */
  public function testDeleteField() {
    $field_label = $this->randomMachineName();
    $field_name_input = 'test';
    $field_name = 'field_test';

    // Create an additional node type.
    $type_name1 = strtolower($this->randomMachineName(8)) . '_test';
    $type1 = $this->drupalCreateContentType(['name' => $type_name1, 'type' => $type_name1]);
    $type_name1 = $type1->id();

    // Create a new field.
    $bundle_path1 = 'admin/structure/types/manage/' . $type_name1;
    $this->fieldUIAddNewField($bundle_path1, $field_name_input, $field_label);

    // Create an additional node type.
    $type_name2 = strtolower($this->randomMachineName(8)) . '_test';
    $type2 = $this->drupalCreateContentType(['name' => $type_name2, 'type' => $type_name2]);
    $type_name2 = $type2->id();

    // Add a field to the second node type.
    $bundle_path2 = 'admin/structure/types/manage/' . $type_name2;
    $this->fieldUIAddExistingField($bundle_path2, $field_name, $field_label);

    \Drupal::service('module_installer')->install(['views']);
    ViewTestData::createTestViews(get_class($this), ['field_test_views']);

    $view = View::load('test_view_field_delete');
    $this->assertNotNull($view);
    $this->assertTrue($view->status());
    // Test that the View depends on the field.
    $dependencies = $view->getDependencies() + ['config' => []];
    $this->assertTrue(in_array("field.storage.node.$field_name", $dependencies['config']));

    // Check the config dependencies of the first field, the field storage must
    // not be shown as being deleted yet.
    $this->drupalGet("$bundle_path1/fields/node.$type_name1.$field_name/delete");
    $this->assertNoText(t('The listed configuration will be deleted.'));
    $this->assertNoText(t('View'));
    $this->assertNoText('test_view_field_delete');

    // Delete the first field.
    $this->fieldUIDeleteField($bundle_path1, "node.$type_name1.$field_name", $field_label, $type_name1);

    // Check that the field was deleted.
    $this->assertNull(FieldConfig::loadByName('node', $type_name1, $field_name), 'Field was deleted.');
    // Check that the field storage was not deleted
    $this->assertNotNull(FieldStorageConfig::loadByName('node', $field_name), 'Field storage was not deleted.');

    // Check the config dependencies of the first field.
    $this->drupalGet("$bundle_path2/fields/node.$type_name2.$field_name/delete");
    $this->assertText(t('The listed configuration will be updated.'));
    $this->assertText(t('View'));
    $this->assertText('test_view_field_delete');

    $xml = $this->cssSelect('#edit-entity-deletes');
    // Test that nothing is scheduled for deletion.
    $this->assertFalse(isset($xml[0]), 'The field currently being deleted is not shown in the entity deletions.');

    // Delete the second field.
    $this->fieldUIDeleteField($bundle_path2, "node.$type_name2.$field_name", $field_label, $type_name2);

    // Check that the field was deleted.
    $this->assertNull(FieldConfig::loadByName('node', $type_name2, $field_name), 'Field was deleted.');
    // Check that the field storage was deleted too.
    $this->assertNull(FieldStorageConfig::loadByName('node', $field_name), 'Field storage was deleted.');

    // Test that the View isn't deleted and has been disabled.
    $view = View::load('test_view_field_delete');
    $this->assertNotNull($view);
    $this->assertFalse($view->status());
    // Test that the View no longer depends on the deleted field.
    $dependencies = $view->getDependencies() + ['config' => []];
    $this->assertFalse(in_array("field.storage.node.$field_name", $dependencies['config']));
  }

}
