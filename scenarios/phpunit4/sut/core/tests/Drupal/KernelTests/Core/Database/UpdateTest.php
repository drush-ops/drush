<?php

namespace Drupal\KernelTests\Core\Database;

/**
 * Tests the update query builder.
 *
 * @group Database
 */
class UpdateTest extends DatabaseTestBase {

  /**
   * Confirms that we can update a single record successfully.
   */
  public function testSimpleUpdate() {
    $num_updated = db_update('test')
      ->fields(['name' => 'Tiffany'])
      ->condition('id', 1)
      ->execute();
    $this->assertIdentical($num_updated, 1, 'Updated 1 record.');

    $saved_name = db_query('SELECT name FROM {test} WHERE id = :id', [':id' => 1])->fetchField();
    $this->assertIdentical($saved_name, 'Tiffany', 'Updated name successfully.');
  }

  /**
   * Confirms updating to NULL.
   */
  public function testSimpleNullUpdate() {
    $this->ensureSampleDataNull();
    $num_updated = db_update('test_null')
      ->fields(['age' => NULL])
      ->condition('name', 'Kermit')
      ->execute();
    $this->assertIdentical($num_updated, 1, 'Updated 1 record.');

    $saved_age = db_query('SELECT age FROM {test_null} WHERE name = :name', [':name' => 'Kermit'])->fetchField();
    $this->assertNull($saved_age, 'Updated name successfully.');
  }

  /**
   * Confirms that we can update multiple records successfully.
   */
  public function testMultiUpdate() {
    $num_updated = db_update('test')
      ->fields(['job' => 'Musician'])
      ->condition('job', 'Singer')
      ->execute();
    $this->assertIdentical($num_updated, 2, 'Updated 2 records.');

    $num_matches = db_query('SELECT COUNT(*) FROM {test} WHERE job = :job', [':job' => 'Musician'])->fetchField();
    $this->assertIdentical($num_matches, '2', 'Updated fields successfully.');
  }

  /**
   * Confirms that we can update multiple records with a non-equality condition.
   */
  public function testMultiGTUpdate() {
    $num_updated = db_update('test')
      ->fields(['job' => 'Musician'])
      ->condition('age', 26, '>')
      ->execute();
    $this->assertIdentical($num_updated, 2, 'Updated 2 records.');

    $num_matches = db_query('SELECT COUNT(*) FROM {test} WHERE job = :job', [':job' => 'Musician'])->fetchField();
    $this->assertIdentical($num_matches, '2', 'Updated fields successfully.');
  }

  /**
   * Confirms that we can update multiple records with a where call.
   */
  public function testWhereUpdate() {
    $num_updated = db_update('test')
      ->fields(['job' => 'Musician'])
      ->where('age > :age', [':age' => 26])
      ->execute();
    $this->assertIdentical($num_updated, 2, 'Updated 2 records.');

    $num_matches = db_query('SELECT COUNT(*) FROM {test} WHERE job = :job', [':job' => 'Musician'])->fetchField();
    $this->assertIdentical($num_matches, '2', 'Updated fields successfully.');
  }

  /**
   * Confirms that we can stack condition and where calls.
   */
  public function testWhereAndConditionUpdate() {
    $update = db_update('test')
      ->fields(['job' => 'Musician'])
      ->where('age > :age', [':age' => 26])
      ->condition('name', 'Ringo');
    $num_updated = $update->execute();
    $this->assertIdentical($num_updated, 1, 'Updated 1 record.');

    $num_matches = db_query('SELECT COUNT(*) FROM {test} WHERE job = :job', [':job' => 'Musician'])->fetchField();
    $this->assertIdentical($num_matches, '1', 'Updated fields successfully.');
  }

  /**
   * Tests updating with expressions.
   */
  public function testExpressionUpdate() {
    // Ensure that expressions are handled properly. This should set every
    // record's age to a square of itself.
    $num_rows = db_update('test')
      ->expression('age', 'age * age')
      ->execute();
    $this->assertIdentical($num_rows, 4, 'Updated 4 records.');

    $saved_name = db_query('SELECT name FROM {test} WHERE age = :age', [':age' => pow(26, 2)])->fetchField();
    $this->assertIdentical($saved_name, 'Paul', 'Successfully updated values using an algebraic expression.');
  }

  /**
   * Tests return value on update.
   */
  public function testUpdateAffectedRows() {
    // At 5am in the morning, all band members but those with a priority 1 task
    // are sleeping. So we set their tasks to 'sleep'. 5 records match the
    // condition and therefore are affected by the query, even though two of
    // them actually don't have to be changed because their value was already
    // 'sleep'. Still, execute() should return 5 affected rows, not only 3,
    // because that's cross-db expected behavior.
    $num_rows = db_update('test_task')
      ->condition('priority', 1, '<>')
      ->fields(['task' => 'sleep'])
      ->execute();
    $this->assertIdentical($num_rows, 5, 'Correctly returned 5 affected rows.');
  }

  /**
   * Confirm that we can update the primary key of a record successfully.
   */
  public function testPrimaryKeyUpdate() {
    $num_updated = db_update('test')
      ->fields(['id' => 42, 'name' => 'John'])
      ->condition('id', 1)
      ->execute();
    $this->assertIdentical($num_updated, 1, 'Updated 1 record.');

    $saved_name = db_query('SELECT name FROM {test} WHERE id = :id', [':id' => 42])->fetchField();
    $this->assertIdentical($saved_name, 'John', 'Updated primary key successfully.');
  }

  /**
   * Confirm that we can update values in a column with special name.
   */
  public function testSpecialColumnUpdate() {
    $num_updated = db_update('test_special_columns')
      ->fields(['offset' => 'New offset value'])
      ->condition('id', 1)
      ->execute();
    $this->assertIdentical($num_updated, 1, 'Updated 1 special column record.');

    $saved_value = db_query('SELECT "offset" FROM {test_special_columns} WHERE id = :id', [':id' => 1])->fetchField();
    $this->assertIdentical($saved_value, 'New offset value', 'Updated special column name value successfully.');
  }

}
