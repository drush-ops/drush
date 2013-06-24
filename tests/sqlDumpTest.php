<?php

/*
 * @file
 * Tests for sql-dump commands.
 *
 * @group commands
 * @group sql
 * @group slow
 */
class SqlDumpTest extends Drush_CommandTestCase {

  /**
   * Test that a dump file is created successfully.
   */
  function testSqlDump() {
    $this->sites = $this->setUpDrupal(1, TRUE);
    $full_dump_file_path = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'full_db.sql';
    $options = array(
      'result-file' => $full_dump_file_path,
      'skip-tables-list' => 'role_permiss*',
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );
    $this->drush('sql-dump', array(), array_merge($options));
    $this->assertFileExists($full_dump_file_path);
    $full_dump_file = file_get_contents($full_dump_file_path);

    // Test that we have sane contents.
    $this->assertContains('sequences', $full_dump_file);
    // Test skip-files-list and wildcard expansion.
    $this->assertNotContains('role_permission', $full_dump_file);
    return $full_dump_file_path;
  }
}
