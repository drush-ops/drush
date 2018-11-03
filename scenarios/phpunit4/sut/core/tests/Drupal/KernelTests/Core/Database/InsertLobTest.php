<?php

namespace Drupal\KernelTests\Core\Database;

/**
 * Tests the Insert query builder with LOB fields.
 *
 * @group Database
 */
class InsertLobTest extends DatabaseTestBase {

  /**
   * Tests that we can insert a single blob field successfully.
   */
  public function testInsertOneBlob() {
    $data = "This is\000a test.";
    $this->assertTrue(strlen($data) === 15, 'Test data contains a NULL.');
    $id = db_insert('test_one_blob')
      ->fields(['blob1' => $data])
      ->execute();
    $r = db_query('SELECT * FROM {test_one_blob} WHERE id = :id', [':id' => $id])->fetchAssoc();
    $this->assertTrue($r['blob1'] === $data, format_string('Can insert a blob: id @id, @data.', ['@id' => $id, '@data' => serialize($r)]));
  }

  /**
   * Tests that we can insert multiple blob fields in the same query.
   */
  public function testInsertMultipleBlob() {
    $id = db_insert('test_two_blobs')
      ->fields([
        'blob1' => 'This is',
        'blob2' => 'a test',
      ])
      ->execute();
    $r = db_query('SELECT * FROM {test_two_blobs} WHERE id = :id', [':id' => $id])->fetchAssoc();
    $this->assertTrue($r['blob1'] === 'This is' && $r['blob2'] === 'a test', 'Can insert multiple blobs per row.');
  }

}
