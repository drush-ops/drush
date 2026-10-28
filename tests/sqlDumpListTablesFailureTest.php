<?php

namespace Unish;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that sql-dump does not run when the tables cannot be listed.
 *
 * With --skip-tables-list (and friends) the selection is expanded against the
 * tables in the database. If listing fails, an empty selection must not be
 * treated as "dump everything", which would write the excluded tables.
 *
 * @see \Drush\Sql\Sqlmysql::listTables()
 * @see \Drush\Sql\SqlBase::dump()
 */
#[Group('commands')]
#[Group('sql')]
#[Group('slow')]
class sqlDumpListTablesFailureTest extends CommandUnishTestCase {

  /**
   * Create a directory of stubs: a failing "mysql" and a recording "mysqldump".
   *
   * @return string[]
   *   The stub directory and the marker file written by the mysqldump stub.
   */
  protected function createClientStubs() {
    $real_mysqldump = trim((string) shell_exec('command -v mysqldump'));
    if ($real_mysqldump === '') {
      $this->markTestSkipped('mysqldump is not available.');
    }

    $dir = UNISH_SANDBOX . '/stubs';
    mkdir($dir);
    $marker = $dir . '/mysqldump-was-called';

    // The mysql client fails, so tables cannot be listed.
    file_put_contents($dir . '/mysql', "#!/bin/sh\necho 'mysql: simulated failure' >&2\nexit 1\n");
    // mysqldump keeps working, but records that it was invoked.
    file_put_contents($dir . '/mysqldump', "#!/bin/sh\ntouch " . escapeshellarg($marker) . "\nexec " . escapeshellarg($real_mysqldump) . " \"\$@\"\n");
    chmod($dir . '/mysql', 0755);
    chmod($dir . '/mysqldump', 0755);

    return array($dir, $marker);
  }

  public function testSqlDumpAbortsWhenTablesCannotBeListed() {
    if ($this->db_driver() != 'mysql') {
      $this->markTestSkipped('Only applies to the mysql driver.');
    }
    if ($this->is_windows()) {
      $this->markTestSkipped('Uses shell script stubs.');
    }

    $this->setUpDrupal(1, TRUE);
    list($stub_dir, $marker) = $this->createClientStubs();

    $dump_file = UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'full_db.sql';
    $options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'result-file' => $dump_file,
      'skip-tables-list' => 'hist*,cache*,router,config*,watchdog,key_valu*',
      'yes' => NULL,
    );
    $env = array('PATH' => $stub_dir . ':' . getenv('PATH'));

    // The requested output file does not exist yet.
    $this->drush('sql-dump', array(), $options, NULL, NULL, self::EXIT_ERROR, NULL, $env);
    $this->assertStringContainsString('Failed to list tables', $this->getErrorOutput());
    $this->assertFileDoesNotExist($marker, 'mysqldump must not be run when tables cannot be listed.');
    $this->assertFileDoesNotExist($dump_file, 'No dump file must be created.');

    // An existing output file must be left alone, not truncated.
    file_put_contents($dump_file, 'previous dump');
    $this->drush('sql-dump', array(), $options, NULL, NULL, self::EXIT_ERROR, NULL, $env);
    $this->assertFileDoesNotExist($marker);
    $this->assertSame('previous dump', file_get_contents($dump_file));
  }
}
