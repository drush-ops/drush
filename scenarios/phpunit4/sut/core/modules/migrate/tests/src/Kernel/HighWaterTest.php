<?php

namespace Drupal\Tests\migrate\Kernel;

/**
 * Tests migration high water property.
 *
 * @group migrate
 */
class HighWaterTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'migrate',
    'migrate_high_water_test',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Create source test table.
    $this->sourceDatabase->schema()->createTable('high_water_node', [
      'fields' => [
        'id' => [
          'description' => 'Serial',
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'changed' => [
          'description' => 'Highwater',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'title' => [
          'description' => 'Title',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
      ],
      'primary key' => [
        'id',
      ],
      'description' => 'Contains nodes to import',
    ]);

    // Add 3 items to source table.
    $this->sourceDatabase->insert('high_water_node')
      ->fields([
        'title',
        'changed',
      ])
      ->values([
        'title' => 'Item 1',
        'changed' => 1,
      ])
      ->values([
        'title' => 'Item 2',
        'changed' => 2,
      ])
      ->values([
        'title' => 'Item 3',
        'changed' => 3,
      ])
      ->execute();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');

    $this->executeMigration('high_water_test');
  }

  /**
   * Tests high water property of SqlBase.
   */
  public function testHighWater() {
    // Assert all of the nodes have been imported.
    $this->assertNodeExists('Item 1');
    $this->assertNodeExists('Item 2');
    $this->assertNodeExists('Item 3');

    // Update Item 1 setting its high_water_property to value that is below
    // current high water mark.
    $this->sourceDatabase->update('high_water_node')
      ->fields([
        'title' => 'Item 1 updated',
        'changed' => 2,
      ])
      ->condition('title', 'Item 1')
      ->execute();

    // Update Item 2 setting its high_water_property to value equal to
    // current high water mark.
    $this->sourceDatabase->update('high_water_node')
      ->fields([
        'title' => 'Item 2 updated',
        'changed' => 3,
      ])
      ->condition('title', 'Item 2')
      ->execute();

    // Update Item 3 setting its high_water_property to value that is above
    // current high water mark.
    $this->sourceDatabase->update('high_water_node')
      ->fields([
        'title' => 'Item 3 updated',
        'changed' => 4,
      ])
      ->condition('title', 'Item 3')
      ->execute();

    // Execute migration again.
    $this->executeMigration('high_water_test');

    // Item with lower highwater should not be updated.
    $this->assertNodeExists('Item 1');
    $this->assertNodeDoesNotExist('Item 1 updated');

    // Item with equal highwater should not be updated.
    $this->assertNodeExists('Item 2');
    $this->assertNodeDoesNotExist('Item 2 updated');

    // Item with greater highwater should be updated.
    $this->assertNodeExists('Item 3 updated');
    $this->assertNodeDoesNotExist('Item 3');
  }

  /**
   * Tests high water property of SqlBase when rows marked for update.
   */
  public function testHighWaterUpdate() {
    // Assert all of the nodes have been imported.
    $this->assertNodeExists('Item 1');
    $this->assertNodeExists('Item 2');
    $this->assertNodeExists('Item 3');

    // Update Item 1 setting its high_water_property to value that is below
    // current high water mark.
    $this->sourceDatabase->update('high_water_node')
      ->fields([
        'title' => 'Item 1 updated',
        'changed' => 2,
      ])
      ->condition('title', 'Item 1')
      ->execute();

    // Update Item 2 setting its high_water_property to value equal to
    // current high water mark.
    $this->sourceDatabase->update('high_water_node')
      ->fields([
        'title' => 'Item 2 updated',
        'changed' => 3,
      ])
      ->condition('title', 'Item 2')
      ->execute();

    // Update Item 3 setting its high_water_property to value that is above
    // current high water mark.
    $this->sourceDatabase->update('high_water_node')
      ->fields([
        'title' => 'Item 3 updated',
        'changed' => 4,
      ])
      ->condition('title', 'Item 3')
      ->execute();

    // Set all rows as needing an update.
    $id_map = $this->getMigration('high_water_test')->getIdMap();
    $id_map->prepareUpdate();

    $this->executeMigration('high_water_test');

    // Item with lower highwater should be updated.
    $this->assertNodeExists('Item 1 updated');
    $this->assertNodeDoesNotExist('Item 1');

    // Item with equal highwater should be updated.
    $this->assertNodeExists('Item 2 updated');
    $this->assertNodeDoesNotExist('Item 2');

    // Item with greater highwater should be updated.
    $this->assertNodeExists('Item 3 updated');
    $this->assertNodeDoesNotExist('Item 3');
  }

  /**
   * Assert that node with given title exists.
   *
   * @param string $title
   *   Title of the node.
   */
  protected function assertNodeExists($title) {
    self::assertTrue($this->nodeExists($title));
  }

  /**
   * Assert that node with given title does not exist.
   *
   * @param string $title
   *   Title of the node.
   */
  protected function assertNodeDoesNotExist($title) {
    self::assertFalse($this->nodeExists($title));
  }

  /**
   * Checks if node with given title exists.
   *
   * @param string $title
   *   Title of the node.
   *
   * @return bool
   */
  protected function nodeExists($title) {
    $query = \Drupal::entityQuery('node');
    $result = $query
      ->condition('title', $title)
      ->range(0, 1)
      ->execute();

    return !empty($result);
  }

}
