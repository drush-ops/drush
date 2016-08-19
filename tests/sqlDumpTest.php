<?php

namespace Unish;

/**
 * Tests for sql-dump commands.
 *
 * @group commands
 * @group sql
 * @group slow
 */
class SqlDumpTest extends CommandUnishTestCase {

  /**
   * Test that a dump file is created successfully.
   */
  function testSqlDump() {
    if ($this->db_driver() == 'sqlite') {
      $this->markTestSkipped('SQL Dump does not apply to SQLite.');
      return;
    }

    $this->setUpDrupal(1, TRUE);
    $root = $this->webroot();
    $uri = 'dev';
    $full_dump_file_path = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'full_db.sql';

    $options = array(
      'result-file' => $full_dump_file_path,
      // Last 5 entries are for D8+
      'skip-tables-list' => 'hist*,cache*,router,config*,watchdog,key_valu*',
      'yes' => NULL,
    );
    $site_selection_options = array(
      'root' => $root,
      'uri' => $uri,
    );

    // Test --extra option
    if ($this->db_driver() == 'mysql') {
      $this->drush('sql-dump', array(), array_merge($options, $site_selection_options, array('extra' => '--skip-add-drop-table')));
      $this->assertFileExists($full_dump_file_path);
      $full_dump_file = file_get_contents($full_dump_file_path);
      $this->assertNotContains('DROP TABLE IF EXISTS', $full_dump_file);
    }


    // First, do a test without any aliases, and dump the whole database
    $this->drush('sql-dump', array(), array_merge($options, $site_selection_options));
    $this->assertFileExists($full_dump_file_path);
    $full_dump_file = file_get_contents($full_dump_file_path);
    // Test that we have sane contents.
    $this->assertContains('queue', $full_dump_file);
    // Test skip-files-list and wildcard expansion.
    $this->assertNotContains('history', $full_dump_file);
    // Next, set up an alias file and run a couple of simulated
    // tests to see if options are propagated correctly.
    // Control: insure options are not set when not specified
    unset($options['skip-tables-list']);
    unlink($full_dump_file_path);
    $this->drush('sql-dump', array(), array_merge($options, $site_selection_options));
    $this->assertFileExists($full_dump_file_path);
    $full_dump_file = file_get_contents($full_dump_file_path);
    // Test that we have sane contents.
    $this->assertContains('queue', $full_dump_file);
    // Test skip-files-list and wildcard expansion.
    $this->assertContains('history', $full_dump_file);

    $aliasPath = UNISH_SANDBOX . '/aliases';
    mkdir($aliasPath);
    $aliasFile = $aliasPath . '/bar.aliases.drushrc.php';
    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['test'] = array(
    'root' => '$root',
    'uri' => '$uri',
    'site' => 'stage',
    'command-specific' => array(
      'sql-dump' => array(
        'skip-tables-list' => 'hist*,cache*,router,config*,watchdog,key_valu*',
      ),
    ),
  );
EOD;
    file_put_contents($aliasFile, $aliasContents);
    $options['alias-path'] = $aliasPath;
    unlink($full_dump_file_path);
    // Now run again with an alias, and test to see if the option is there
    $this->drush('sql-dump', array(), array_merge($options), '@test');
    $this->assertFileExists($full_dump_file_path);
    $full_dump_file = file_get_contents($full_dump_file_path);
    // Test that we have sane contents.
    $this->assertContains('queue', $full_dump_file);
    // Test skip-files-list and wildcard expansion.
    $this->assertNotContains('history', $full_dump_file);
    // Repeat control test:  options not recovered in absence of an alias.
    unlink($full_dump_file_path);
    $this->drush('sql-dump', array(), array_merge($options, $site_selection_options));
    $this->assertFileExists($full_dump_file_path);
    $full_dump_file = file_get_contents($full_dump_file_path);
    // Test that we have sane contents.
    $this->assertContains('queue', $full_dump_file);
    // Test skip-files-list and wildcard expansion.
    $this->assertContains('history', $full_dump_file);
    // Now run yet with @self, and test to see that Drush can recover the option
    // --skip-tables-list, defined in @test.
    unlink($full_dump_file_path);
    $this->drush('sql-dump', array(), array_merge($options, $site_selection_options), '@self');
    $this->assertFileExists($full_dump_file_path);
    $full_dump_file = file_get_contents($full_dump_file_path);
    // Test that we have sane contents.
    $this->assertContains('queue', $full_dump_file);
    // Test skip-files-list and wildcard expansion.
    $this->assertNotContains('history', $full_dump_file);
  }
}
