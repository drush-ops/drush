<?php

namespace Drupal\Tests\config\Functional;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the user interface for importing/exporting configuration.
 *
 * Each testX method does a complete rebuild of a Drupal site, so values being
 * tested need to be stored in protected properties in order to survive until
 * the next rebuild.
 *
 * @group config
 */
class ConfigExportImportUITest extends BrowserTestBase {

  /**
   * The contents of the config export tarball, held between test methods.
   *
   * @var string
   */
  protected $tarball;

  /**
   * Holds the original 'site slogan' before testing.
   *
   * @var string
   */
  protected $originalSlogan;

  /**
   * Holds a randomly generated new 'site slogan' for testing.
   *
   * @var string
   */
  protected $newSlogan;


  /**
   * Holds a content type.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $contentType;

  /**
   * Holds the randomly-generated name of a field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Holds the field storage entity for $fieldName.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['config', 'node', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // The initial import must be done with uid 1 because if separately named
    // roles are created then the role is lost after import. If the roles
    // created have the same name then the sync will fail because they will
    // have different UUIDs.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests a simple site export import case.
   */
  public function testExportImport() {
    // After installation there is no snapshot and nothing to import.
    $this->drupalGet('admin/config/development/configuration');
    $this->assertNoText(t('Warning message'));
    $this->assertText(t('There are no configuration changes to import.'));

    $this->originalSlogan = $this->config('system.site')->get('slogan');
    $this->newSlogan = $this->randomString(16);
    $this->assertNotEqual($this->newSlogan, $this->originalSlogan);
    $this->config('system.site')
      ->set('slogan', $this->newSlogan)
      ->save();
    $this->assertEqual($this->config('system.site')->get('slogan'), $this->newSlogan);

    // Create a content type.
    $this->contentType = $this->drupalCreateContentType();

    // Create a field.
    $this->fieldName = mb_strtolower($this->randomMachineName());
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => 'text',
    ]);
    $this->fieldStorage->save();
    FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $this->contentType->id(),
    ])->save();
    // Update the displays so that configuration does not change unexpectedly on
    // import.
    entity_get_form_display('node', $this->contentType->id(), 'default')
      ->setComponent($this->fieldName, [
        'type' => 'text_textfield',
      ])
      ->save();
    entity_get_display('node', $this->contentType->id(), 'full')
      ->setComponent($this->fieldName)
      ->save();
    entity_get_display('node', $this->contentType->id(), 'default')
      ->setComponent($this->fieldName)
      ->save();
    entity_get_display('node', $this->contentType->id(), 'teaser')
      ->removeComponent($this->fieldName)
      ->save();

    $this->drupalGet('node/add/' . $this->contentType->id());
    $this->assertFieldByName("{$this->fieldName}[0][value]", '', 'Widget is displayed');

    // Export the configuration.
    $this->drupalPostForm('admin/config/development/configuration/full/export', [], 'Export');
    $this->tarball = $this->getSession()->getPage()->getContent();

    $this->config('system.site')
      ->set('slogan', $this->originalSlogan)
      ->save();
    $this->assertEqual($this->config('system.site')->get('slogan'), $this->originalSlogan);

    // Delete the custom field.
    $fields = FieldConfig::loadMultiple();
    foreach ($fields as $field) {
      if ($field->getName() == $this->fieldName) {
        $field->delete();
      }
    }
    $field_storages = FieldStorageConfig::loadMultiple();
    foreach ($field_storages as $field_storage) {
      if ($field_storage->getName() == $this->fieldName) {
        $field_storage->delete();
      }
    }
    $this->drupalGet('node/add/' . $this->contentType->id());
    $this->assertNoFieldByName("{$this->fieldName}[0][value]", '', 'Widget is not displayed');

    // Import the configuration.
    $filename = 'temporary://' . $this->randomMachineName();
    file_put_contents($filename, $this->tarball);
    $this->drupalPostForm('admin/config/development/configuration/full/import', ['files[import_tarball]' => $filename], 'Upload');
    // There is no snapshot yet because an import has never run.
    $this->assertNoText(t('Warning message'));
    $this->assertNoText(t('There are no configuration changes to import.'));
    $this->assertText($this->contentType->label());

    $this->drupalPostForm(NULL, [], 'Import all');
    // After importing the snapshot has been updated an there are no warnings.
    $this->assertNoText(t('Warning message'));
    $this->assertText(t('There are no configuration changes to import.'));

    $this->assertEqual($this->config('system.site')->get('slogan'), $this->newSlogan);

    $this->drupalGet('node/add');
    $this->assertFieldByName("{$this->fieldName}[0][value]", '', 'Widget is displayed');

    $this->config('system.site')
      ->set('slogan', $this->originalSlogan)
      ->save();
    $this->drupalGet('admin/config/development/configuration');
    $this->assertText(t('Warning message'));
    $this->assertText('The following items in your active configuration have changes since the last import that may be lost on the next import.');
    // Ensure the item is displayed as part of a list (to avoid false matches
    // on the rest of the page) and that the list markup is not escaped.
    $this->assertRaw('<li>system.site</li>');
    // Remove everything from sync. The warning about differences between the
    // active and snapshot should no longer exist.
    \Drupal::service('config.storage.sync')->deleteAll();
    $this->drupalGet('admin/config/development/configuration');
    $this->assertNoText(t('Warning message'));
    $this->assertNoText('The following items in your active configuration have changes since the last import that may be lost on the next import.');
    $this->assertText(t('There are no configuration changes to import.'));
    // Write a file to sync. The warning about differences between the active
    // and snapshot should now exist.
    /** @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = $this->container->get('config.storage.sync');
    $data = $this->config('system.site')->get();
    $data['slogan'] = 'in the face';
    $this->copyConfig($this->container->get('config.storage'), $sync);
    $sync->write('system.site', $data);
    $this->drupalGet('admin/config/development/configuration');
    $this->assertText(t('Warning message'));
    $this->assertText('The following items in your active configuration have changes since the last import that may be lost on the next import.');
    // Ensure the item is displayed as part of a list (to avoid false matches
    // on the rest of the page) and that the list markup is not escaped.
    $this->assertRaw('<li>system.site</li>');
  }

  /**
   * Tests an export and import of collections.
   */
  public function testExportImportCollections() {

    /** @var \Drupal\Core\Config\StorageInterface $active_storage */
    $active_storage = \Drupal::service('config.storage');
    $test1_storage = $active_storage->createCollection('collection.test1');
    $test1_storage->write('config_test.create', ['foo' => 'bar']);
    $test1_storage->write('config_test.update', ['foo' => 'bar']);
    $test2_storage = $active_storage->createCollection('collection.test2');
    $test2_storage->write('config_test.another_create', ['foo' => 'bar']);
    $test2_storage->write('config_test.another_update', ['foo' => 'bar']);

    // Export the configuration.
    $this->drupalPostForm('admin/config/development/configuration/full/export', [], 'Export');
    $this->tarball = $this->getSession()->getPage()->getContent();
    $filename = file_directory_temp() . '/' . $this->randomMachineName();
    file_put_contents($filename, $this->tarball);

    // Set up the active storage collections to test import.
    $test1_storage->delete('config_test.create');
    $test1_storage->write('config_test.update', ['foo' => 'baz']);
    $test1_storage->write('config_test.delete', ['foo' => 'bar']);
    $test2_storage->delete('config_test.another_create');
    $test2_storage->write('config_test.another_update', ['foo' => 'baz']);
    $test2_storage->write('config_test.another_delete', ['foo' => 'bar']);

    // Create a snapshot.
    $snapshot_storage = \Drupal::service('config.storage.snapshot');
    \Drupal::service('config.manager')->createSnapshot($active_storage, $snapshot_storage);

    // Ensure that the snapshot has the expected collection data before import.
    $test1_snapshot = $snapshot_storage->createCollection('collection.test1');
    $data = $test1_snapshot->read('config_test.delete');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.delete in collection.test1 exists in the snapshot storage.');
    $data = $test1_snapshot->read('config_test.update');
    $this->assertEqual($data, ['foo' => 'baz'], 'The config_test.update in collection.test1 exists in the snapshot storage.');
    $this->assertFalse($test1_snapshot->read('config_test.create'), 'The config_test.create in collection.test1 does not exist in the snapshot storage.');
    $test2_snapshot = $snapshot_storage->createCollection('collection.test2');
    $data = $test2_snapshot->read('config_test.another_delete');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.another_delete in collection.test2 exists in the snapshot storage.');
    $data = $test2_snapshot->read('config_test.another_update');
    $this->assertEqual($data, ['foo' => 'baz'], 'The config_test.another_update in collection.test2 exists in the snapshot storage.');
    $this->assertFalse($test2_snapshot->read('config_test.another_create'), 'The config_test.another_create in collection.test2 does not exist in the snapshot storage.');

    // Create the tar that contains the expected content for the collections.
    $tar = new ArchiveTar($filename, 'gz');
    $content_list = $tar->listContent();
    // Convert the list of files into something easy to search.
    $files = [];
    foreach ($content_list as $file) {
      $files[] = $file['filename'];
    }
    $this->assertTrue(in_array('collection/test1/config_test.create.yml', $files), 'Config export contains collection/test1/config_test.create.yml.');
    $this->assertTrue(in_array('collection/test2/config_test.another_create.yml', $files), 'Config export contains collection/test2/config_test.another_create.yml.');
    $this->assertTrue(in_array('collection/test1/config_test.update.yml', $files), 'Config export contains collection/test1/config_test.update.yml.');
    $this->assertTrue(in_array('collection/test2/config_test.another_update.yml', $files), 'Config export contains collection/test2/config_test.another_update.yml.');
    $this->assertFalse(in_array('collection/test1/config_test.delete.yml', $files), 'Config export does not contain collection/test1/config_test.delete.yml.');
    $this->assertFalse(in_array('collection/test2/config_test.another_delete.yml', $files), 'Config export does not contain collection/test2/config_test.another_delete.yml.');

    $this->drupalPostForm('admin/config/development/configuration/full/import', ['files[import_tarball]' => $filename], 'Upload');
    // Verify that there are configuration differences to import.
    $this->drupalGet('admin/config/development/configuration');
    $this->assertNoText(t('There are no configuration changes to import.'));
    $this->assertText(t('@collection configuration collection', ['@collection' => 'collection.test1']));
    $this->assertText(t('@collection configuration collection', ['@collection' => 'collection.test2']));
    $this->assertText('config_test.create');
    $this->assertLinkByHref('admin/config/development/configuration/sync/diff_collection/collection.test1/config_test.create');
    $this->assertText('config_test.update');
    $this->assertLinkByHref('admin/config/development/configuration/sync/diff_collection/collection.test1/config_test.update');
    $this->assertText('config_test.delete');
    $this->assertLinkByHref('admin/config/development/configuration/sync/diff_collection/collection.test1/config_test.delete');
    $this->assertText('config_test.another_create');
    $this->assertLinkByHref('admin/config/development/configuration/sync/diff_collection/collection.test2/config_test.another_create');
    $this->assertText('config_test.another_update');
    $this->assertLinkByHref('admin/config/development/configuration/sync/diff_collection/collection.test2/config_test.another_update');
    $this->assertText('config_test.another_delete');
    $this->assertLinkByHref('admin/config/development/configuration/sync/diff_collection/collection.test2/config_test.another_delete');

    $this->drupalPostForm(NULL, [], 'Import all');
    $this->assertText(t('There are no configuration changes to import.'));

    // Test data in collections.
    $data = $test1_storage->read('config_test.create');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.create in collection.test1 has been created.');
    $data = $test1_storage->read('config_test.update');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.update in collection.test1 has been updated.');
    $this->assertFalse($test1_storage->read('config_test.delete'), 'The config_test.delete in collection.test1 has been deleted.');

    $data = $test2_storage->read('config_test.another_create');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.another_create in collection.test2 has been created.');
    $data = $test2_storage->read('config_test.another_update');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.another_update in collection.test2 has been updated.');
    $this->assertFalse($test2_storage->read('config_test.another_delete'), 'The config_test.another_delete in collection.test2 has been deleted.');

    // Ensure that the snapshot has been updated with the collection data.
    $snapshot_storage = \Drupal::service('config.storage.snapshot');
    $test1_snapshot = $snapshot_storage->createCollection('collection.test1');
    $data = $test1_snapshot->read('config_test.create');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.create in collection.test1 has been created in the snapshot storage.');
    $data = $test1_snapshot->read('config_test.update');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.update in collection.test1 has been updated in the snapshot storage.');
    $this->assertFalse($test1_snapshot->read('config_test.delete'), 'The config_test.delete in collection.test1 does not exist in the snapshot storage.');
    $test2_snapshot = $snapshot_storage->createCollection('collection.test2');
    $data = $test2_snapshot->read('config_test.another_create');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.another_create in collection.test2 has been created in the snapshot storage.');
    $data = $test2_snapshot->read('config_test.another_update');
    $this->assertEqual($data, ['foo' => 'bar'], 'The config_test.another_update in collection.test2 has been updated in the snapshot storage.');
    $this->assertFalse($test2_snapshot->read('config_test.another_delete'), 'The config_test.another_delete in collection.test2 does not exist in the snapshot storage.');
  }

}
