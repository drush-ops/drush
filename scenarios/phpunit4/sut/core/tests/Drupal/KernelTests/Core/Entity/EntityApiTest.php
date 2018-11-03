<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\user\UserInterface;

/**
 * Tests basic CRUD functionality.
 *
 * @group Entity
 */
class EntityApiTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    foreach (entity_test_entity_types() as $entity_type_id) {
      // The entity_test schema is installed by the parent.
      if ($entity_type_id != 'entity_test') {
        $this->installEntitySchema($entity_type_id);
      }
    }
  }

  /**
   * Tests basic CRUD functionality of the Entity API.
   */
  public function testCRUD() {
    // All entity variations have to have the same results.
    foreach (entity_test_entity_types() as $entity_type) {
      $this->assertCRUD($entity_type, $this->createUser());
    }
  }

  /**
   * Executes a test set for a defined entity type and user.
   *
   * @param string $entity_type
   *   The entity type to run the tests with.
   * @param \Drupal\user\UserInterface $user1
   *   The user to run the tests with.
   */
  protected function assertCRUD($entity_type, UserInterface $user1) {
    // Create some test entities.
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create(['name' => 'test', 'user_id' => $user1->id()]);
    $entity->save();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create(['name' => 'test2', 'user_id' => $user1->id()]);
    $entity->save();
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type)
      ->create(['name' => 'test', 'user_id' => NULL]);
    $entity->save();

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entity_type);

    $entities = array_values($storage->loadByProperties(['name' => 'test']));
    $this->assertEqual($entities[0]->name->value, 'test', format_string('%entity_type: Created and loaded entity', ['%entity_type' => $entity_type]));
    $this->assertEqual($entities[1]->name->value, 'test', format_string('%entity_type: Created and loaded entity', ['%entity_type' => $entity_type]));

    // Test loading a single entity.
    $loaded_entity = $storage->load($entity->id());
    $this->assertEqual($loaded_entity->id(), $entity->id(), format_string('%entity_type: Loaded a single entity by id.', ['%entity_type' => $entity_type]));

    // Test deleting an entity.
    $entities = array_values($storage->loadByProperties(['name' => 'test2']));
    $entities[0]->delete();
    $entities = array_values($storage->loadByProperties(['name' => 'test2']));
    $this->assertEqual($entities, [], format_string('%entity_type: Entity deleted.', ['%entity_type' => $entity_type]));

    // Test updating an entity.
    $entities = array_values($storage->loadByProperties(['name' => 'test']));
    $entities[0]->name->value = 'test3';
    $entities[0]->save();
    $entity = $storage->load($entities[0]->id());
    $this->assertEqual($entity->name->value, 'test3', format_string('%entity_type: Entity updated.', ['%entity_type' => $entity_type]));

    // Try deleting multiple test entities by deleting all.
    $ids = array_keys($storage->loadMultiple());
    entity_delete_multiple($entity_type, $ids);

    $all = $storage->loadMultiple();
    $this->assertTrue(empty($all), format_string('%entity_type: Deleted all entities.', ['%entity_type' => $entity_type]));

    // Verify that all data got deleted.
    $definition = \Drupal::entityManager()->getDefinition($entity_type);
    $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $definition->getBaseTable() . '}')->fetchField(), 'Base table was emptied');
    if ($data_table = $definition->getDataTable()) {
      $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $data_table . '}')->fetchField(), 'Data table was emptied');
    }
    if ($revision_table = $definition->getRevisionTable()) {
      $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $revision_table . '}')->fetchField(), 'Data table was emptied');
    }
    if ($revision_data_table = $definition->getRevisionDataTable()) {
      $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $revision_data_table . '}')->fetchField(), 'Data table was emptied');
    }

    // Test deleting a list of entities not indexed by entity id.
    $entities = [];
    $entity = entity_create($entity_type, ['name' => 'test', 'user_id' => $user1->id()]);
    $entity->save();
    $entities['test'] = $entity;
    $entity = entity_create($entity_type, ['name' => 'test2', 'user_id' => $user1->id()]);
    $entity->save();
    $entities['test2'] = $entity;
    $controller = \Drupal::entityManager()->getStorage($entity_type);
    $controller->delete($entities);

    // Verify that entities got deleted.
    $all = $storage->loadMultiple();
    $this->assertTrue(empty($all), format_string('%entity_type: Deleted all entities.', ['%entity_type' => $entity_type]));

    // Verify that all data got deleted from the tables.
    $definition = \Drupal::entityManager()->getDefinition($entity_type);
    $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $definition->getBaseTable() . '}')->fetchField(), 'Base table was emptied');
    if ($data_table = $definition->getDataTable()) {
      $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $data_table . '}')->fetchField(), 'Data table was emptied');
    }
    if ($revision_table = $definition->getRevisionTable()) {
      $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $revision_table . '}')->fetchField(), 'Data table was emptied');
    }
    if ($revision_data_table = $definition->getRevisionDataTable()) {
      $this->assertEqual(0, db_query('SELECT COUNT(*) FROM {' . $revision_data_table . '}')->fetchField(), 'Data table was emptied');
    }
  }

  /**
   * Tests that exceptions are thrown when saving or deleting an entity.
   */
  public function testEntityStorageExceptionHandling() {
    $entity = EntityTest::create(['name' => 'test']);
    try {
      $GLOBALS['entity_test_throw_exception'] = TRUE;
      $entity->save();
      $this->fail('Entity presave EntityStorageException thrown but not caught.');
    }
    catch (EntityStorageException $e) {
      $this->assertEqual($e->getcode(), 1, 'Entity presave EntityStorageException caught.');
    }

    $entity = EntityTest::create(['name' => 'test2']);
    try {
      unset($GLOBALS['entity_test_throw_exception']);
      $entity->save();
      $this->pass('Exception presave not thrown and not caught.');
    }
    catch (EntityStorageException $e) {
      $this->assertNotEqual($e->getCode(), 1, 'Entity presave EntityStorageException caught.');
    }

    $entity = EntityTest::create(['name' => 'test3']);
    $entity->save();
    try {
      $GLOBALS['entity_test_throw_exception'] = TRUE;
      $entity->delete();
      $this->fail('Entity predelete EntityStorageException not thrown.');
    }
    catch (EntityStorageException $e) {
      $this->assertEqual($e->getCode(), 2, 'Entity predelete EntityStorageException caught.');
    }

    unset($GLOBALS['entity_test_throw_exception']);
    $entity = EntityTest::create(['name' => 'test4']);
    $entity->save();
    try {
      $entity->delete();
      $this->pass('Entity predelete EntityStorageException not thrown and not caught.');
    }
    catch (EntityStorageException $e) {
      $this->assertNotEqual($e->getCode(), 2, 'Entity predelete EntityStorageException thrown.');
    }
  }

  /**
   * Tests that resaving a revision with a different revision ID throws an exception.
   */
  public function testUpdateWithRevisionId() {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev');

    // Create a new entity.
    /** @var \Drupal\entity_test\Entity\EntityTestMulRev $entity */
    $entity = $storage->create(['name' => 'revision_test']);
    $entity->save();

    $this->setExpectedException(EntityStorageException::class, "Update existing 'entity_test_mulrev' entity revision while changing the revision ID is not supported.");

    $entity->revision_id = 60;
    $entity->save();
  }

  /**
   * Tests that resaving an entity with a different entity ID throws an exception.
   */
  public function testUpdateWithId() {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev');

    // Create a new entity.
    /** @var \Drupal\entity_test\Entity\EntityTestMulRev $entity */
    $entity = $storage->create(['name' => 'revision_test']);
    $entity->save();

    $this->setExpectedException(EntityStorageException::class, "Update existing 'entity_test_mulrev' entity while changing the ID is not supported.");

    $entity->id = 60;
    $entity->save();
  }

}
