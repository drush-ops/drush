<?php

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\SchemaException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Component\Utility\Unicode;

/**
 * Tests table creation and modification via the schema API.
 *
 * @coversDefaultClass \Drupal\Core\Database\Schema
 *
 * @group Database
 */
class SchemaTest extends KernelTestBase {

  /**
   * A global counter for table and field creation.
   *
   * @var int
   */
  protected $counter;

  /**
   * Connection to the database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Database schema instance.
   *
   * @var \Drupal\Core\Database\Schema
   */
  protected $schema;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->connection = Database::getConnection();
    $this->schema = $this->connection->schema();
  }

  /**
   * Tests database interactions.
   */
  public function testSchema() {
    // Try creating a table.
    $table_specification = [
      'description' => 'Schema table description may contain "quotes" and could be long—very long indeed.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'default' => NULL,
        ],
        'test_field'  => [
          'type' => 'int',
          'not null' => TRUE,
          'description' => 'Schema table description may contain "quotes" and could be long—very long indeed. There could be "multiple quoted regions".',
        ],
        'test_field_string'  => [
          'type' => 'varchar',
          'length' => 20,
          'not null' => TRUE,
          'default' => "'\"funky default'\"",
          'description' => 'Schema column description for string.',
        ],
        'test_field_string_ascii'  => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'description' => 'Schema column description for ASCII string.',
        ],
      ],
    ];
    $this->schema->createTable('test_table', $table_specification);

    // Assert that the table exists.
    $this->assertTrue($this->schema->tableExists('test_table'), 'The table exists.');

    // Assert that the table comment has been set.
    $this->checkSchemaComment($table_specification['description'], 'test_table');

    // Assert that the column comment has been set.
    $this->checkSchemaComment($table_specification['fields']['test_field']['description'], 'test_table', 'test_field');

    if ($this->connection->databaseType() === 'mysql') {
      // Make sure that varchar fields have the correct collation.
      $columns = $this->connection->query('SHOW FULL COLUMNS FROM {test_table}');
      foreach ($columns as $column) {
        if ($column->Field == 'test_field_string') {
          $string_check = ($column->Collation == 'utf8mb4_general_ci' || $column->Collation == 'utf8mb4_0900_ai_ci');
        }
        if ($column->Field == 'test_field_string_ascii') {
          $string_ascii_check = ($column->Collation == 'ascii_general_ci');
        }
      }
      $this->assertTrue(!empty($string_check), 'string field has the right collation.');
      $this->assertTrue(!empty($string_ascii_check), 'ASCII string field has the right collation.');
    }

    // An insert without a value for the column 'test_table' should fail.
    $this->assertFalse($this->tryInsert(), 'Insert without a default failed.');

    // Add a default value to the column.
    $this->schema->fieldSetDefault('test_table', 'test_field', 0);
    // The insert should now succeed.
    $this->assertTrue($this->tryInsert(), 'Insert with a default succeeded.');

    // Remove the default.
    $this->schema->fieldSetNoDefault('test_table', 'test_field');
    // The insert should fail again.
    $this->assertFalse($this->tryInsert(), 'Insert without a default failed.');

    // Test for fake index and test for the boolean result of indexExists().
    $index_exists = $this->schema->indexExists('test_table', 'test_field');
    $this->assertIdentical($index_exists, FALSE, 'Fake index does not exist');
    // Add index.
    $this->schema->addIndex('test_table', 'test_field', ['test_field'], $table_specification);
    // Test for created index and test for the boolean result of indexExists().
    $index_exists = $this->schema->indexExists('test_table', 'test_field');
    $this->assertIdentical($index_exists, TRUE, 'Index created.');

    // Rename the table.
    $this->schema->renameTable('test_table', 'test_table2');

    // Index should be renamed.
    $index_exists = $this->schema->indexExists('test_table2', 'test_field');
    $this->assertTrue($index_exists, 'Index was renamed.');

    // We need the default so that we can insert after the rename.
    $this->schema->fieldSetDefault('test_table2', 'test_field', 0);
    $this->assertFalse($this->tryInsert(), 'Insert into the old table failed.');
    $this->assertTrue($this->tryInsert('test_table2'), 'Insert into the new table succeeded.');

    // We should have successfully inserted exactly two rows.
    $count = $this->connection->query('SELECT COUNT(*) FROM {test_table2}')->fetchField();
    $this->assertEqual($count, 2, 'Two fields were successfully inserted.');

    // Try to drop the table.
    $this->schema->dropTable('test_table2');
    $this->assertFalse($this->schema->tableExists('test_table2'), 'The dropped table does not exist.');

    // Recreate the table.
    $this->schema->createTable('test_table', $table_specification);
    $this->schema->fieldSetDefault('test_table', 'test_field', 0);
    $this->schema->addField('test_table', 'test_serial', ['type' => 'int', 'not null' => TRUE, 'default' => 0, 'description' => 'Added column description.']);

    // Assert that the column comment has been set.
    $this->checkSchemaComment('Added column description.', 'test_table', 'test_serial');

    // Change the new field to a serial column.
    $this->schema->changeField('test_table', 'test_serial', 'test_serial', ['type' => 'serial', 'not null' => TRUE, 'description' => 'Changed column description.'], ['primary key' => ['test_serial']]);

    // Assert that the column comment has been set.
    $this->checkSchemaComment('Changed column description.', 'test_table', 'test_serial');

    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max1 = $this->connection->query('SELECT MAX(test_serial) FROM {test_table}')->fetchField();
    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max2 = $this->connection->query('SELECT MAX(test_serial) FROM {test_table}')->fetchField();
    $this->assertTrue($max2 > $max1, 'The serial is monotone.');

    $count = $this->connection->query('SELECT COUNT(*) FROM {test_table}')->fetchField();
    $this->assertEqual($count, 2, 'There were two rows.');

    // Test adding a serial field to an existing table.
    $this->schema->dropTable('test_table');
    $this->schema->createTable('test_table', $table_specification);
    $this->schema->fieldSetDefault('test_table', 'test_field', 0);
    $this->schema->addField('test_table', 'test_serial', ['type' => 'serial', 'not null' => TRUE], ['primary key' => ['test_serial']]);

    // Test the primary key columns.
    $method = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');
    $method->setAccessible(TRUE);
    $this->assertSame(['test_serial'], $method->invoke($this->schema, 'test_table'));

    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max1 = $this->connection->query('SELECT MAX(test_serial) FROM {test_table}')->fetchField();
    $this->assertTrue($this->tryInsert(), 'Insert with a serial succeeded.');
    $max2 = $this->connection->query('SELECT MAX(test_serial) FROM {test_table}')->fetchField();
    $this->assertTrue($max2 > $max1, 'The serial is monotone.');

    $count = $this->connection->query('SELECT COUNT(*) FROM {test_table}')->fetchField();
    $this->assertEqual($count, 2, 'There were two rows.');

    // Test adding a new column and form a composite primary key with it.
    $this->schema->addField('test_table', 'test_composite_primary_key', ['type' => 'int', 'not null' => TRUE, 'default' => 0], ['primary key' => ['test_serial', 'test_composite_primary_key']]);

    // Test the primary key columns.
    $this->assertSame(['test_serial', 'test_composite_primary_key'], $method->invoke($this->schema, 'test_table'));

    // Test renaming of keys and constraints.
    $this->schema->dropTable('test_table');
    $table_specification = [
      'fields' => [
        'id'  => [
          'type' => 'serial',
          'not null' => TRUE,
        ],
        'test_field'  => [
          'type' => 'int',
          'default' => 0,
        ],
      ],
      'primary key' => ['id'],
      'unique keys' => [
        'test_field' => ['test_field'],
      ],
    ];
    $this->schema->createTable('test_table', $table_specification);

    // Tests for indexes are Database specific.
    $db_type = $this->connection->databaseType();

    // Test for existing primary and unique keys.
    switch ($db_type) {
      case 'pgsql':
        $primary_key_exists = $this->schema->constraintExists('test_table', '__pkey');
        $unique_key_exists = $this->schema->constraintExists('test_table', 'test_field' . '__key');
        break;

      case 'sqlite':
        // SQLite does not create a standalone index for primary keys.
        $primary_key_exists = TRUE;
        $unique_key_exists = $this->schema->indexExists('test_table', 'test_field');
        break;

      default:
        $primary_key_exists = $this->schema->indexExists('test_table', 'PRIMARY');
        $unique_key_exists = $this->schema->indexExists('test_table', 'test_field');
        break;
    }
    $this->assertIdentical($primary_key_exists, TRUE, 'Primary key created.');
    $this->assertIdentical($unique_key_exists, TRUE, 'Unique key created.');

    $this->schema->renameTable('test_table', 'test_table2');

    // Test for renamed primary and unique keys.
    switch ($db_type) {
      case 'pgsql':
        $renamed_primary_key_exists = $this->schema->constraintExists('test_table2', '__pkey');
        $renamed_unique_key_exists = $this->schema->constraintExists('test_table2', 'test_field' . '__key');
        break;

      case 'sqlite':
        // SQLite does not create a standalone index for primary keys.
        $renamed_primary_key_exists = TRUE;
        $renamed_unique_key_exists = $this->schema->indexExists('test_table2', 'test_field');
        break;

      default:
        $renamed_primary_key_exists = $this->schema->indexExists('test_table2', 'PRIMARY');
        $renamed_unique_key_exists = $this->schema->indexExists('test_table2', 'test_field');
        break;
    }
    $this->assertIdentical($renamed_primary_key_exists, TRUE, 'Primary key was renamed.');
    $this->assertIdentical($renamed_unique_key_exists, TRUE, 'Unique key was renamed.');

    // For PostgreSQL check in addition that sequence was renamed.
    if ($db_type == 'pgsql') {
      // Get information about new table.
      $info = $this->schema->queryTableInformation('test_table2');
      $sequence_name = $this->schema->prefixNonTable('test_table2', 'id', 'seq');
      $this->assertEqual($sequence_name, current($info->sequences), 'Sequence was renamed.');
    }

    // Use database specific data type and ensure that table is created.
    $table_specification = [
      'description' => 'Schema table description.',
      'fields' => [
        'timestamp'  => [
          'mysql_type' => 'timestamp',
          'pgsql_type' => 'timestamp',
          'sqlite_type' => 'datetime',
          'not null' => FALSE,
          'default' => NULL,
        ],
      ],
    ];
    try {
      $this->schema->createTable('test_timestamp', $table_specification);
    }
    catch (\Exception $e) {
    }
    $this->assertTrue($this->schema->tableExists('test_timestamp'), 'Table with database specific datatype was created.');
  }

  /**
   * Tests that indexes on string fields are limited to 191 characters on MySQL.
   *
   * @see \Drupal\Core\Database\Driver\mysql\Schema::getNormalizedIndexes()
   */
  public function testIndexLength() {
    if ($this->connection->databaseType() !== 'mysql') {
      $this->markTestSkipped("The '{$this->connection->databaseType()}' database type does not support setting column length for indexes.");
    }

    $table_specification = [
      'fields' => [
        'id'  => [
          'type' => 'int',
          'default' => NULL,
        ],
        'test_field_text'  => [
          'type' => 'text',
          'not null' => TRUE,
        ],
        'test_field_string_long'  => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'test_field_string_ascii_long'  => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'test_field_string_short'  => [
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
      ],
      'indexes' => [
        'test_regular' => [
          'test_field_text',
          'test_field_string_long',
          'test_field_string_ascii_long',
          'test_field_string_short',
        ],
        'test_length' => [
          ['test_field_text', 128],
          ['test_field_string_long', 128],
          ['test_field_string_ascii_long', 128],
          ['test_field_string_short', 128],
        ],
        'test_mixed' => [
          ['test_field_text', 200],
          'test_field_string_long',
          ['test_field_string_ascii_long', 200],
          'test_field_string_short',
        ],
      ],
    ];
    $this->schema->createTable('test_table_index_length', $table_specification);

    // Ensure expected exception thrown when adding index with missing info.
    $expected_exception_message = "MySQL needs the 'test_field_text' field specification in order to normalize the 'test_regular' index";
    $missing_field_spec = $table_specification;
    unset($missing_field_spec['fields']['test_field_text']);
    try {
      $this->schema->addIndex('test_table_index_length', 'test_separate', [['test_field_text', 200]], $missing_field_spec);
      $this->fail('SchemaException not thrown when adding index with missing information.');
    }
    catch (SchemaException $e) {
      $this->assertEqual($expected_exception_message, $e->getMessage());
    }

    // Add a separate index.
    $this->schema->addIndex('test_table_index_length', 'test_separate', [['test_field_text', 200]], $table_specification);
    $table_specification_with_new_index = $table_specification;
    $table_specification_with_new_index['indexes']['test_separate'] = [['test_field_text', 200]];

    // Ensure that the exceptions of addIndex are thrown as expected.
    try {
      $this->schema->addIndex('test_table_index_length', 'test_separate', [['test_field_text', 200]], $table_specification);
      $this->fail('\Drupal\Core\Database\SchemaObjectExistsException exception missed.');
    }
    catch (SchemaObjectExistsException $e) {
      $this->pass('\Drupal\Core\Database\SchemaObjectExistsException thrown when index already exists.');
    }

    try {
      $this->schema->addIndex('test_table_non_existing', 'test_separate', [['test_field_text', 200]], $table_specification);
      $this->fail('\Drupal\Core\Database\SchemaObjectDoesNotExistException exception missed.');
    }
    catch (SchemaObjectDoesNotExistException $e) {
      $this->pass('\Drupal\Core\Database\SchemaObjectDoesNotExistException thrown when index already exists.');
    }

    // Get index information.
    $results = $this->connection->query('SHOW INDEX FROM {test_table_index_length}');
    $expected_lengths = [
      'test_regular' => [
        'test_field_text' => 191,
        'test_field_string_long' => 191,
        'test_field_string_ascii_long' => NULL,
        'test_field_string_short' => NULL,
      ],
      'test_length' => [
        'test_field_text' => 128,
        'test_field_string_long' => 128,
        'test_field_string_ascii_long' => 128,
        'test_field_string_short' => NULL,
      ],
      'test_mixed' => [
        'test_field_text' => 191,
        'test_field_string_long' => 191,
        'test_field_string_ascii_long' => 200,
        'test_field_string_short' => NULL,
      ],
      'test_separate' => [
        'test_field_text' => 191,
      ],
    ];

    // Count the number of columns defined in the indexes.
    $column_count = 0;
    foreach ($table_specification_with_new_index['indexes'] as $index) {
      foreach ($index as $field) {
        $column_count++;
      }
    }
    $test_count = 0;
    foreach ($results as $result) {
      $this->assertEqual($result->Sub_part, $expected_lengths[$result->Key_name][$result->Column_name], 'Index length matches expected value.');
      $test_count++;
    }
    $this->assertEqual($test_count, $column_count, 'Number of tests matches expected value.');
  }

  /**
   * Tests inserting data into an existing table.
   *
   * @param string $table
   *   The database table to insert data into.
   *
   * @return bool
   *   TRUE if the insert succeeded, FALSE otherwise.
   */
  public function tryInsert($table = 'test_table') {
    try {
      $this->connection
        ->insert($table)
        ->fields(['id' => mt_rand(10, 20)])
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Checks that a table or column comment matches a given description.
   *
   * @param $description
   *   The asserted description.
   * @param $table
   *   The table to test.
   * @param $column
   *   Optional column to test.
   */
  public function checkSchemaComment($description, $table, $column = NULL) {
    if (method_exists($this->schema, 'getComment')) {
      $comment = $this->schema->getComment($table, $column);
      // The schema comment truncation for mysql is different.
      if ($this->connection->databaseType() === 'mysql') {
        $max_length = $column ? 255 : 60;
        $description = Unicode::truncate($description, $max_length, TRUE, TRUE);
      }
      $this->assertEqual($comment, $description, 'The comment matches the schema description.');
    }
  }

  /**
   * Tests creating unsigned columns and data integrity thereof.
   */
  public function testUnsignedColumns() {
    // First create the table with just a serial column.
    $table_name = 'unsigned_table';
    $table_spec = [
      'fields' => ['serial_column' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE]],
      'primary key' => ['serial_column'],
    ];
    $this->schema->createTable($table_name, $table_spec);

    // Now set up columns for the other types.
    $types = ['int', 'float', 'numeric'];
    foreach ($types as $type) {
      $column_spec = ['type' => $type, 'unsigned' => TRUE];
      if ($type == 'numeric') {
        $column_spec += ['precision' => 10, 'scale' => 0];
      }
      $column_name = $type . '_column';
      $table_spec['fields'][$column_name] = $column_spec;
      $this->schema->addField($table_name, $column_name, $column_spec);
    }

    // Finally, check each column and try to insert invalid values into them.
    foreach ($table_spec['fields'] as $column_name => $column_spec) {
      $this->assertTrue($this->schema->fieldExists($table_name, $column_name), format_string('Unsigned @type column was created.', ['@type' => $column_spec['type']]));
      $this->assertFalse($this->tryUnsignedInsert($table_name, $column_name), format_string('Unsigned @type column rejected a negative value.', ['@type' => $column_spec['type']]));
    }
  }

  /**
   * Tries to insert a negative value into columns defined as unsigned.
   *
   * @param string $table_name
   *   The table to insert.
   * @param string $column_name
   *   The column to insert.
   *
   * @return bool
   *   TRUE if the insert succeeded, FALSE otherwise.
   */
  public function tryUnsignedInsert($table_name, $column_name) {
    try {
      $this->connection
        ->insert($table_name)
        ->fields([$column_name => -1])
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Tests adding columns to an existing table with default and initial value.
   */
  public function testSchemaAddFieldDefaultInitial() {
    // Test varchar types.
    foreach ([1, 32, 128, 256, 512] as $length) {
      $base_field_spec = [
        'type' => 'varchar',
        'length' => $length,
      ];
      $variations = [
        ['not null' => FALSE],
        ['not null' => FALSE, 'default' => '7'],
        ['not null' => FALSE, 'default' => substr('"thing"', 0, $length)],
        ['not null' => FALSE, 'default' => substr("\"'hing", 0, $length)],
        ['not null' => TRUE, 'initial' => 'd'],
        ['not null' => FALSE, 'default' => NULL],
        ['not null' => TRUE, 'initial' => 'd', 'default' => '7'],
      ];

      foreach ($variations as $variation) {
        $field_spec = $variation + $base_field_spec;
        $this->assertFieldAdditionRemoval($field_spec);
      }
    }

    // Test int and float types.
    foreach (['int', 'float'] as $type) {
      foreach (['tiny', 'small', 'medium', 'normal', 'big'] as $size) {
        $base_field_spec = [
          'type' => $type,
          'size' => $size,
        ];
        $variations = [
          ['not null' => FALSE],
          ['not null' => FALSE, 'default' => 7],
          ['not null' => TRUE, 'initial' => 1],
          ['not null' => TRUE, 'initial' => 1, 'default' => 7],
          ['not null' => TRUE, 'initial_from_field' => 'serial_column'],
          [
            'not null' => TRUE,
            'initial_from_field' => 'test_nullable_field',
            'initial'  => 100,
          ],
        ];

        foreach ($variations as $variation) {
          $field_spec = $variation + $base_field_spec;
          $this->assertFieldAdditionRemoval($field_spec);
        }
      }
    }

    // Test numeric types.
    foreach ([1, 5, 10, 40, 65] as $precision) {
      foreach ([0, 2, 10, 30] as $scale) {
        // Skip combinations where precision is smaller than scale.
        if ($precision <= $scale) {
          continue;
        }

        $base_field_spec = [
          'type' => 'numeric',
          'scale' => $scale,
          'precision' => $precision,
        ];
        $variations = [
          ['not null' => FALSE],
          ['not null' => FALSE, 'default' => 7],
          ['not null' => TRUE, 'initial' => 1],
          ['not null' => TRUE, 'initial' => 1, 'default' => 7],
          ['not null' => TRUE, 'initial_from_field' => 'serial_column'],
        ];

        foreach ($variations as $variation) {
          $field_spec = $variation + $base_field_spec;
          $this->assertFieldAdditionRemoval($field_spec);
        }
      }
    }
  }

  /**
   * Asserts that a given field can be added and removed from a table.
   *
   * The addition test covers both defining a field of a given specification
   * when initially creating at table and extending an existing table.
   *
   * @param $field_spec
   *   The schema specification of the field.
   */
  protected function assertFieldAdditionRemoval($field_spec) {
    // Try creating the field on a new table.
    $table_name = 'test_table_' . ($this->counter++);
    $table_spec = [
      'fields' => [
        'serial_column' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
        'test_nullable_field' => ['type' => 'int', 'not null' => FALSE],
        'test_field' => $field_spec,
      ],
      'primary key' => ['serial_column'],
    ];
    $this->schema->createTable($table_name, $table_spec);
    $this->pass(format_string('Table %table created.', ['%table' => $table_name]));

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $field_spec);

    // Clean-up.
    $this->schema->dropTable($table_name);

    // Try adding a field to an existing table.
    $table_name = 'test_table_' . ($this->counter++);
    $table_spec = [
      'fields' => [
        'serial_column' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
        'test_nullable_field' => ['type' => 'int', 'not null' => FALSE],
      ],
      'primary key' => ['serial_column'],
    ];
    $this->schema->createTable($table_name, $table_spec);
    $this->pass(format_string('Table %table created.', ['%table' => $table_name]));

    // Insert some rows to the table to test the handling of initial values.
    for ($i = 0; $i < 3; $i++) {
      $this->connection
        ->insert($table_name)
        ->useDefaults(['serial_column'])
        ->fields(['test_nullable_field' => 100])
        ->execute();
    }

    // Add another row with no value for the 'test_nullable_field' column.
    $this->connection
      ->insert($table_name)
      ->useDefaults(['serial_column'])
      ->execute();

    $this->schema->addField($table_name, 'test_field', $field_spec);
    $this->pass(format_string('Column %column created.', ['%column' => 'test_field']));

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $field_spec);

    // Clean-up.
    $this->schema->dropField($table_name, 'test_field');

    // Add back the field and then try to delete a field which is also a primary
    // key.
    $this->schema->addField($table_name, 'test_field', $field_spec);
    $this->schema->dropField($table_name, 'serial_column');
    $this->schema->dropTable($table_name);
  }

  /**
   * Asserts that a newly added field has the correct characteristics.
   */
  protected function assertFieldCharacteristics($table_name, $field_name, $field_spec) {
    // Check that the initial value has been registered.
    if (isset($field_spec['initial'])) {
      // There should be no row with a value different then $field_spec['initial'].
      $count = $this->connection
        ->select($table_name)
        ->fields($table_name, ['serial_column'])
        ->condition($field_name, $field_spec['initial'], '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEqual($count, 0, 'Initial values filled out.');
    }

    // Check that the initial value from another field has been registered.
    if (isset($field_spec['initial_from_field']) && !isset($field_spec['initial'])) {
      // There should be no row with a value different than
      // $field_spec['initial_from_field'].
      $count = $this->connection
        ->select($table_name)
        ->fields($table_name, ['serial_column'])
        ->where($table_name . '.' . $field_spec['initial_from_field'] . ' <> ' . $table_name . '.' . $field_name)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEqual($count, 0, 'Initial values from another field filled out.');
    }
    elseif (isset($field_spec['initial_from_field']) && isset($field_spec['initial'])) {
      // There should be no row with a value different than '100'.
      $count = $this->connection
        ->select($table_name)
        ->fields($table_name, ['serial_column'])
        ->condition($field_name, 100, '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->assertEqual($count, 0, 'Initial values from another field or a default value filled out.');
    }

    // Check that the default value has been registered.
    if (isset($field_spec['default'])) {
      // Try inserting a row, and check the resulting value of the new column.
      $id = $this->connection
        ->insert($table_name)
        ->useDefaults(['serial_column'])
        ->execute();
      $field_value = $this->connection
        ->select($table_name)
        ->fields($table_name, [$field_name])
        ->condition('serial_column', $id)
        ->execute()
        ->fetchField();
      $this->assertEqual($field_value, $field_spec['default'], 'Default value registered.');
    }
  }

  /**
   * Tests various schema changes' effect on the table's primary key.
   *
   * @param array $initial_primary_key
   *   The initial primary key of the test table.
   * @param array $renamed_primary_key
   *   The primary key of the test table after renaming the test field.
   *
   * @dataProvider providerTestSchemaCreateTablePrimaryKey
   *
   * @covers ::addField
   * @covers ::changeField
   * @covers ::dropField
   * @covers ::findPrimaryKeyColumns
   */
  public function testSchemaChangePrimaryKey(array $initial_primary_key, array $renamed_primary_key) {
    $find_primary_key_columns = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');
    $find_primary_key_columns->setAccessible(TRUE);

    // Test making the field the primary key of the table upon creation.
    $table_name = 'test_table';
    $table_spec = [
      'fields' => [
        'test_field' => ['type' => 'int', 'not null' => TRUE],
        'other_test_field' => ['type' => 'int', 'not null' => TRUE],
      ],
      'primary key' => $initial_primary_key,
    ];
    $this->schema->createTable($table_name, $table_spec);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Change the field type and make sure the primary key stays in place.
    $this->schema->changeField($table_name, 'test_field', 'test_field', ['type' => 'varchar', 'length' => 32, 'not null' => TRUE]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Add some data and change the field type back, to make sure that changing
    // the type leaves the primary key in place even with existing data.
    $this->connection
      ->insert($table_name)
      ->fields(['test_field' => 1, 'other_test_field' => 2])
      ->execute();
    $this->schema->changeField($table_name, 'test_field', 'test_field', ['type' => 'int', 'not null' => TRUE]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Make sure that adding the primary key can be done as part of changing
    // a field, as well.
    $this->schema->dropPrimaryKey($table_name);
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));
    $this->schema->changeField($table_name, 'test_field', 'test_field', ['type' => 'int', 'not null' => TRUE], ['primary key' => $initial_primary_key]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Rename the field and make sure the primary key was updated.
    $this->schema->changeField($table_name, 'test_field', 'test_field_renamed', ['type' => 'int', 'not null' => TRUE]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field_renamed'));
    $this->assertEquals($renamed_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Drop the field and make sure the primary key was dropped, as well.
    $this->schema->dropField($table_name, 'test_field_renamed');
    $this->assertFalse($this->schema->fieldExists($table_name, 'test_field_renamed'));
    $this->assertEquals([], $find_primary_key_columns->invoke($this->schema, $table_name));

    // Add the field again and make sure adding the primary key can be done at
    // the same time.
    $this->schema->addField($table_name, 'test_field', ['type' => 'int', 'default' => 0, 'not null' => TRUE], ['primary key' => $initial_primary_key]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));

    // Drop the field again and explicitly add a primary key.
    $this->schema->dropField($table_name, 'test_field');
    $this->schema->addPrimaryKey($table_name, ['other_test_field']);
    $this->assertFalse($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals(['other_test_field'], $find_primary_key_columns->invoke($this->schema, $table_name));

    // Test that adding a field with a primary key will work even with a
    // pre-existing primary key.
    $this->schema->addField($table_name, 'test_field', ['type' => 'int', 'default' => 0, 'not null' => TRUE], ['primary key' => $initial_primary_key]);
    $this->assertTrue($this->schema->fieldExists($table_name, 'test_field'));
    $this->assertEquals($initial_primary_key, $find_primary_key_columns->invoke($this->schema, $table_name));
  }

  /**
   * Provides test cases for SchemaTest::testSchemaCreateTablePrimaryKey().
   *
   * @return array
   *   An array of test cases for SchemaTest::testSchemaCreateTablePrimaryKey().
   */
  public function providerTestSchemaCreateTablePrimaryKey() {
    $tests = [];

    $tests['simple_primary_key'] = [
      'initial_primary_key' => ['test_field'],
      'renamed_primary_key' => ['test_field_renamed'],
    ];
    $tests['composite_primary_key'] = [
      'initial_primary_key' => ['test_field', 'other_test_field'],
      'renamed_primary_key' => ['test_field_renamed', 'other_test_field'],
    ];
    $tests['composite_primary_key_different_order'] = [
      'initial_primary_key' => ['other_test_field', 'test_field'],
      'renamed_primary_key' => ['other_test_field', 'test_field_renamed'],
    ];

    return $tests;
  }

  /**
   * Tests an invalid field specification as a primary key on table creation.
   */
  public function testInvalidPrimaryKeyOnTableCreation() {
    // Test making an invalid field the primary key of the table upon creation.
    $table_name = 'test_table';
    $table_spec = [
      'fields' => [
        'test_field' => ['type' => 'int'],
      ],
      'primary key' => ['test_field'],
    ];
    $this->setExpectedException(SchemaException::class, "The 'test_field' field specification does not define 'not null' as TRUE.");
    $this->schema->createTable($table_name, $table_spec);
  }

  /**
   * Tests adding an invalid field specification as a primary key.
   */
  public function testInvalidPrimaryKeyAddition() {
    // Test adding a new invalid field to the primary key.
    $table_name = 'test_table';
    $table_spec = [
      'fields' => [
        'test_field' => ['type' => 'int', 'not null' => TRUE],
      ],
      'primary key' => ['test_field'],
    ];
    $this->schema->createTable($table_name, $table_spec);

    $this->setExpectedException(SchemaException::class, "The 'new_test_field' field specification does not define 'not null' as TRUE.");
    $this->schema->addField($table_name, 'new_test_field', ['type' => 'int'], ['primary key' => ['test_field', 'new_test_field']]);
  }

  /**
   * Tests changing the primary key with an invalid field specification.
   */
  public function testInvalidPrimaryKeyChange() {
    // Test adding a new invalid field to the primary key.
    $table_name = 'test_table';
    $table_spec = [
      'fields' => [
        'test_field' => ['type' => 'int', 'not null' => TRUE],
      ],
      'primary key' => ['test_field'],
    ];
    $this->schema->createTable($table_name, $table_spec);

    $this->setExpectedException(SchemaException::class, "The 'changed_test_field' field specification does not define 'not null' as TRUE.");
    $this->schema->dropPrimaryKey($table_name);
    $this->schema->changeField($table_name, 'test_field', 'changed_test_field', ['type' => 'int'], ['primary key' => ['changed_test_field']]);
  }

  /**
   * Tests changing columns between types with default and initial values.
   */
  public function testSchemaChangeFieldDefaultInitial() {
    $field_specs = [
      ['type' => 'int', 'size' => 'normal', 'not null' => FALSE],
      ['type' => 'int', 'size' => 'normal', 'not null' => TRUE, 'initial' => 1, 'default' => 17],
      ['type' => 'float', 'size' => 'normal', 'not null' => FALSE],
      ['type' => 'float', 'size' => 'normal', 'not null' => TRUE, 'initial' => 1, 'default' => 7.3],
      ['type' => 'numeric', 'scale' => 2, 'precision' => 10, 'not null' => FALSE],
      ['type' => 'numeric', 'scale' => 2, 'precision' => 10, 'not null' => TRUE, 'initial' => 1, 'default' => 7],
    ];

    foreach ($field_specs as $i => $old_spec) {
      foreach ($field_specs as $j => $new_spec) {
        if ($i === $j) {
          // Do not change a field into itself.
          continue;
        }
        $this->assertFieldChange($old_spec, $new_spec);
      }
    }

    $field_specs = [
      ['type' => 'varchar_ascii', 'length' => '255'],
      ['type' => 'varchar', 'length' => '255'],
      ['type' => 'text'],
      ['type' => 'blob', 'size' => 'big'],
    ];

    foreach ($field_specs as $i => $old_spec) {
      foreach ($field_specs as $j => $new_spec) {
        if ($i === $j) {
          // Do not change a field into itself.
          continue;
        }
        // Note if the serialized data contained an object this would fail on
        // Postgres.
        // @see https://www.drupal.org/node/1031122
        $this->assertFieldChange($old_spec, $new_spec, serialize(['string' => "This \n has \\\\ some backslash \"*string action.\\n"]));
      }
    }

  }

  /**
   * Asserts that a field can be changed from one spec to another.
   *
   * @param $old_spec
   *   The beginning field specification.
   * @param $new_spec
   *   The ending field specification.
   */
  protected function assertFieldChange($old_spec, $new_spec, $test_data = NULL) {
    $table_name = 'test_table_' . ($this->counter++);
    $table_spec = [
      'fields' => [
        'serial_column' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
        'test_field' => $old_spec,
      ],
      'primary key' => ['serial_column'],
    ];
    $this->schema->createTable($table_name, $table_spec);
    $this->pass(format_string('Table %table created.', ['%table' => $table_name]));

    // Check the characteristics of the field.
    $this->assertFieldCharacteristics($table_name, 'test_field', $old_spec);

    // Remove inserted rows.
    $this->connection->truncate($table_name)->execute();

    if ($test_data) {
      $id = $this->connection
        ->insert($table_name)
        ->fields(['test_field'], [$test_data])
        ->execute();
    }

    // Change the field.
    $this->schema->changeField($table_name, 'test_field', 'test_field', $new_spec);

    if ($test_data) {
      $field_value = $this->connection
        ->select($table_name)
        ->fields($table_name, ['test_field'])
        ->condition('serial_column', $id)
        ->execute()
        ->fetchField();
      $this->assertIdentical($field_value, $test_data);
    }

    // Check the field was changed.
    $this->assertFieldCharacteristics($table_name, 'test_field', $new_spec);

    // Clean-up.
    $this->schema->dropTable($table_name);
  }

  /**
   * @covers ::findPrimaryKeyColumns
   */
  public function testFindPrimaryKeyColumns() {
    $method = new \ReflectionMethod(get_class($this->schema), 'findPrimaryKeyColumns');
    $method->setAccessible(TRUE);

    // Test with single column primary key.
    $this->schema->createTable('table_with_pk_0', [
      'description' => 'Table with primary key.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
    ]);
    $this->assertSame(['id'], $method->invoke($this->schema, 'table_with_pk_0'));

    // Test with multiple column primary key.
    $this->schema->createTable('table_with_pk_1', [
      'description' => 'Table with primary key with multiple columns.',
      'fields' => [
        'id0'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'id1'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id0', 'id1'],
    ]);
    $this->assertSame(['id0', 'id1'], $method->invoke($this->schema, 'table_with_pk_1'));

    // Test with multiple column primary key and not being the first column of
    // the table definition.
    $this->schema->createTable('table_with_pk_2', [
      'description' => 'Table with primary key with multiple columns at the end and in reverted sequence.',
      'fields' => [
        'test_field_1'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field_2'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'id3'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'id4'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id4', 'id3'],
    ]);
    $this->assertSame(['id4', 'id3'], $method->invoke($this->schema, 'table_with_pk_2'));

    // Test with multiple column primary key in a different order. For the
    // PostgreSQL and the SQLite drivers is sorting used to get the primary key
    // columns in the right order.
    $this->schema->createTable('table_with_pk_3', [
      'description' => 'Table with primary key with multiple columns at the end and in reverted sequence.',
      'fields' => [
        'test_field_1'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field_2'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'id3'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'id4'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id3', 'test_field_2', 'id4'],
    ]);
    $this->assertSame(['id3', 'test_field_2', 'id4'], $method->invoke($this->schema, 'table_with_pk_3'));

    // Test with table without a primary key.
    $this->schema->createTable('table_without_pk_1', [
      'description' => 'Table without primary key.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
    ]);
    $this->assertSame([], $method->invoke($this->schema, 'table_without_pk_1'));

    // Test with table with an empty primary key.
    $this->schema->createTable('table_without_pk_2', [
      'description' => 'Table without primary key.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
        'test_field'  => [
          'type' => 'int',
          'not null' => TRUE,
        ],
      ],
      'primary key' => [],
    ]);
    $this->assertSame([], $method->invoke($this->schema, 'table_without_pk_2'));

    // Test with non existing table.
    $this->assertFalse($method->invoke($this->schema, 'non_existing_table'));
  }

  /**
   * Tests the findTables() method.
   */
  public function testFindTables() {
    // We will be testing with three tables, two of them using the default
    // prefix and the third one with an individually specified prefix.
    // Set up a new connection with different connection info.
    $connection_info = Database::getConnectionInfo();

    // Add per-table prefix to the second table.
    $new_connection_info = $connection_info['default'];
    $new_connection_info['prefix']['test_2_table'] = $new_connection_info['prefix']['default'] . '_shared_';
    Database::addConnectionInfo('test', 'default', $new_connection_info);
    Database::setActiveConnection('test');
    $test_schema = Database::getConnection()->schema();

    // Create the tables.
    $table_specification = [
      'description' => 'Test table.',
      'fields' => [
        'id'  => [
          'type' => 'int',
          'default' => NULL,
        ],
      ],
    ];
    $test_schema->createTable('test_1_table', $table_specification);
    $test_schema->createTable('test_2_table', $table_specification);
    $test_schema->createTable('the_third_table', $table_specification);

    // Check the "all tables" syntax.
    $tables = $test_schema->findTables('%');
    sort($tables);
    $expected = [
      // The 'config' table is added by
      // \Drupal\KernelTests\KernelTestBase::containerBuild().
      'config',
      'test_1_table',
      // This table uses a per-table prefix, yet it is returned as un-prefixed.
      'test_2_table',
      'the_third_table',
    ];
    $this->assertEqual($tables, $expected, 'All tables were found.');

    // Check the restrictive syntax.
    $tables = $test_schema->findTables('test_%');
    sort($tables);
    $expected = [
      'test_1_table',
      'test_2_table',
    ];
    $this->assertEqual($tables, $expected, 'Two tables were found.');

    // Go back to the initial connection.
    Database::setActiveConnection('default');
  }

}
