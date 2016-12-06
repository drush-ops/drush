<?php

namespace Drush\Commands\core;

use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;

/**
 * Class Sanitizer.
 *
 * @package Drupal\sanitize
 */
class SanitizeCommands extends DrushCommands {

  /**
   * @var bool
   *   Whether database table names should be wrapped in brackets for prefixing.
   */
  protected $wrap;

  /**
   * Sets $this->wrap to TRUE if a db-prefix is set with drush.
   */
  protected function setWrap() {
    $this->wrap = $wrap_table_name = (bool) drush_get_option('db-prefix');
  }

  /**
   * Runs all sanitizing methods.
   */
  public function sanitize($major_version) {
    $this->setWrap();
    $this->sanitizeSessions();

    if ($major_version == 8) {
      $this->sanitizeComments();
      $this->sanitizeUserFields();
    }
  }

  /**
   * Sanitize string fields associated with the user.
   *
   * We've got to do a good bit of SQL-foo here because Drupal services are
   * not yet available.
   */
  public function sanitizeUserFields() {
    /** @var SqlBase $sql_class */
    $sql_class = drush_sql_get_class();
    $tables = $sql_class->listTables();
    $whitelist_fields = (array) explode(',', drush_get_option('whitelist-fields'));

    foreach ($tables as $table) {
      if (strpos($table, 'user__field_') === 0) {
        $field_name = substr($table, 6, strlen($table));
        if (in_array($field_name, $whitelist_fields)) {
          continue;
        }

        $output = $this->query("SELECT data FROM config WHERE name = 'field.field.user.user.$field_name';");
        $field_config = unserialize($output[0]);
        $field_type = $field_config['field_type'];
        $randomizer = new Random();

        switch ($field_type) {

          case 'email':
            $this->sanitizeTableColumn($table,  $field_name . '_value', $randomizer->name(10) . '@example.com');
            break;

          case 'string':
            $this->sanitizeTableColumn($table,  $field_name . '_value', $randomizer->name(255));
            break;

          case 'string_long':
            $this->sanitizeTableColumn($table,  $field_name . '_value', $randomizer->sentences(1));
            break;

          case 'telephone':
            $this->sanitizeTableColumn($table,  $field_name . '_value', '15555555555');
            break;

          case 'text':
            $this->sanitizeTableColumn($table,  $field_name . '_value', $randomizer->paragraphs(2));
            break;

          case 'text_long':
            $this->sanitizeTableColumn($table,  $field_name . '_value', $randomizer->paragraphs(10));
            break;

          case 'text_with_summary':
            // This isn't working?
            $this->sanitizeTableColumn($table,  $field_name . '_value', $randomizer->paragraphs(2));
            $this->sanitizeTableColumn($table,  $field_name . '_summary', $randomizer->name(255));
            break;
        }
      }
    }
  }

  /**
   * Replaces all values in given table column with the specified value.
   *
   * @param string $table
   *   The database table name.
   * @param string $column
   *   The database column to be updated.
   * @param $value
   *   The new value.
   */
  public function sanitizeTableColumn($table, $column, $value) {
    $table_name_wrapped = $this->wrapTableName($table);
    $sql = "UPDATE $table_name_wrapped SET $column='$value';";
    drush_sql_register_post_sync_op($table.$column, dt("Replaces all values in $table table with the same random long string."), $sql);
  }

  /**
   * Truncates the session table.
   */
  public function sanitizeSessions() {
    // Seems quite portable (SQLite?) - http://en.wikipedia.org/wiki/Truncate_(SQL)
    $table_name = $this->wrapTableName('sessions');
    $sql_sessions = "TRUNCATE TABLE $table_name;";
    drush_sql_register_post_sync_op('sessions', dt('Truncate Drupal\'s sessions table'), $sql_sessions);
  }

  /**
   * Sanitizes comments_field_data table.
   */
  public function sanitizeComments() {

    $comments_enabled = $this->query("SHOW TABLES LIKE 'comment_field_data';");
    if (!$comments_enabled) {
      return;
    }

    $comments_table = $this->wrapTableName('comment_field_data');
    $sql_comments = "UPDATE $comments_table SET name='Anonymous', mail='', homepage='http://example.com' WHERE uid = 0;";
    drush_sql_register_post_sync_op('anon_comments', dt('Remove names and email addresses from anonymous user comments.'), $sql_comments);

    $sql_comments = "UPDATE $comments_table SET name=CONCAT('User', `uid`), mail=CONCAT('user+', `uid`, '@example.com'), homepage='http://example.com' WHERE uid <> 0;";
    drush_sql_register_post_sync_op('auth_comments', dt('Replace names and email addresses from authenticated user comments.'), $sql_comments);
  }

  /**
   * Wraps a table name in brackets if a database prefix is being used.
   *
   * @param string $table_name
   *   The name of the database table.
   *
   * @return string
   *   The (possibly wrapped) table name.
   */
  public function wrapTableName($table_name) {
    if ($this->wrap) {
      $processed = '{' . $table_name . '}';
    }
    else {
      $processed = $table_name;
    }

    return $processed;
  }

  /**
   * Executes a sql command using drush sqlq and returns the output.
   *
   * @param string $query
   *   The SQL query to execute. Must end in a semicolon!
   *
   * @return string
   *   The output of the query.
   */
  protected function query($query) {
    $current = drush_get_context('DRUSH_SIMULATE');
    drush_set_context('DRUSH_SIMULATE', FALSE);
    $success = $this->executeQuery($query);
    $output = drush_shell_exec_output();
    drush_set_context('DRUSH_SIMULATE', $current);

    return $output;
  }

  /**
   * Executes a sql command using drush sqlq.
   *
   * Command output can be grabbed using drush_shell_exec_output().
   *
   * @param string $query
   *   The SQL query to execute. Must end in a semicolon!
   *
   * @return bool
   *   TRUE if command executed successfully.
   *
   * @throws \Drush\Sql\SqlException
   */
  protected function executeQuery($query) {
    $sql = drush_sql_get_class();
    return $sql->query($query);
  }

}

