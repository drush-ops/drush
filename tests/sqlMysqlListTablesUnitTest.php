<?php

namespace Unish;

use Drush\Sql\Sqlmysql;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for parsing the table list returned by the mysql client.
 *
 * @see \Drush\Sql\Sqlmysql::listTables()
 */
#[Group('base')]
#[Group('sql')]
class sqlMysqlListTablesUnitTest extends UnitUnishTestCase {

  public function set_up() {
    // Bootstrap to ensure the autoloader is running so that Sqlmysql is found.
    drush_preflight();
  }

  /**
   * Call the protected parser on a Sqlmysql instance.
   */
  protected function parse($output) {
    $sql = new Sqlmysql(array('driver' => 'mysql'));
    $method = new \ReflectionMethod($sql, 'parseTableList');
    return $method->invoke($sql, $output);
  }

  public function testSplitsLinesAndSkipsEmpty() {
    $this->assertSame(array('cache', 'node', 'users'), $this->parse("cache\nnode\n\nusers\n"));
  }

  public function testHandlesWindowsLineEndings() {
    $this->assertSame(array('cache', 'node'), $this->parse("cache\r\nnode\r\n"));
  }

  public function testEmptyOutput() {
    $this->assertSame(array(), $this->parse(''));
  }

  /**
   * Table names with non-ASCII characters must not be split.
   *
   * "Å" is C3 85 in UTF-8. PCRE's \R matches the lone byte 0x85 when not in
   * UTF mode, which would turn "Ångstrom" into two bogus entries.
   */
  public function testDoesNotSplitUtf8TableNames() {
    $this->assertSame(array('Ångstrom', 'users', 'テーブル'), $this->parse("Ångstrom\nusers\nテーブル\n"));
  }
}
