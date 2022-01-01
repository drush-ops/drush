<?php

namespace Unish;

/**
  * Unit tests for drush_sql_expand_wildcard_tables and
  *   drush_sql_filter_tables.
  *
  * @group base
  * @group sql
  */
class WildcardUnitCase extends UnitUnishTestCase {

  public static function set_up_before_class() {
    parent::set_up_before_class();
    require_once(dirname(__FILE__) . '/../commands/sql/sql.drush.inc');
  }

  /**
   * Tests drush_sql_expand_wildcard_tables().
   *
   * @see drush_sql_expand_wildcard_tables().
   */
  public function testExpandWildcardTables() {
    // Array of tables to search for.
    $wildcard_input = array(
      'cache*',
    );
    // Mock array of tables to test with. This is
    // also the expected result.
    $db_tables = array(
      'cache',
      'cache_bootstrap',
      'cache_field',
      'cache_filter',
      'cache_form',
      'cache_menu',
      'cache_page',
      'cache_path',
      'cache_update',
      'example',
    );

    $expanded_db_tables = drush_sql_expand_wildcard_tables($wildcard_input, $db_tables);
    // We expect all but the last table to match.
    array_pop($db_tables);
    $this->assertEquals($db_tables, $expanded_db_tables);
  }

  /**
   * Tests drush_sql_filter_tables().
   *
   * @see drush_sql_filter_tables().
   */
  public function testFilterTables() {
    // Array of tables to search for.
    $wildcard_input = array(
      'cache',
      'cache_*',
    );
    // Mock array of tables to test with.
    $db_tables = array(
      'cache',
      'cache_bootstrap',
      'cache_field',
      'cache_filter',
      'cache_form',
      'cache_menu',
      'cache_page',
      'cache_path',
      'cache_update',
    );
    $expected_result = array(
      'cache',
    );

    $actual_result = drush_sql_filter_tables($wildcard_input, $db_tables);
    $this->assertEquals($expected_result, $actual_result);
  }
}
