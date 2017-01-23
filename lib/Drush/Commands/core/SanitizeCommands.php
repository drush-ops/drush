<?php

namespace Drush\Commands\core;

use Drupal\Component\Utility\Random;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;

/**
 * Class SanitizeCommands
 * @package Drush\Commands\core
 */
class SanitizeCommands {

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
   * Sanitize the database by removed and obfuscating user data.
   *
   * @command sql-sanitize
   *
   * @todo "drush dependencies" array('sqlsync')
   *
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @description Run sanitization operations on the current database.
   * @option db-prefix Enable replacement of braces in sanitize queries.
   * @option db-url A Drupal 6 style database URL. E.g.,
   *   mysql://root:pass@127.0.0.1/db
   * @option sanitize-email The pattern for test email addresses in the
   *   sanitization operation, or "no" to keep email addresses unchanged. May
   *   contain replacement patterns %uid, %mail or %name. Example value:
   *   user+%uid@localhost
   * @option sanitize-password The password to assign to all accounts in the
   *   sanitization operation, or "no" to keep passwords unchanged. Example
   *   value: password
   * @option whitelist-fields A comma delimited list of fields exempt from sanitization.
   * @aliases sqlsan
   * @usage drush sql-sanitize --sanitize-password=no
   *   Sanitize database without modifying any passwords.
   * @usage drush sql-sanitize --whitelist-fields=field_biography,field_phone_number
   *   Sanitizes database but exempts two user fields from modification.
   * @see hook_drush_sql_sync_sanitize() for adding custom sanitize routines.
   */
  public function sqlSanitize($options = [
    'db-prefix' => FALSE,
    'db-url' => '',
    'sanitize-email' => '',
    'sanitize-password' => '',
    'whitelist-fields' => '',
    ]) {
    drush_sql_bootstrap_further();
    if ($options['db-prefix']) {
      drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
    }

    // Drush itself implements this via sql_drush_sql_sync_sanitize().
    drush_command_invoke_all('drush_sql_sync_sanitize', 'default');
    $operations = drush_get_context('post-sync-ops');
    if (!empty($operations)) {
      if (!drush_get_context('DRUSH_SIMULATE')) {
        $messages = _drush_sql_get_post_sync_messages();
        if ($messages) {
          drush_print();
          drush_print($messages);
        }
      }
      $queries = array_column($operations, 'query');
      $sanitize_query = implode(" ", $queries);
    }
    if (!drush_confirm(dt('Do you really want to sanitize the current database?'))) {
      return drush_user_abort();
    }

    if ($sanitize_query) {
      $sql = drush_sql_get_class();
      $sanitize_query = $sql->query_prefix($sanitize_query);
      $result = $sql->query($sanitize_query);
      if (!$result) {
        throw new \Exception(dt('Sanitize query failed.'));
      }
    }
  }

  /**
   * Performs database sanitization.
   *
   * @param int $major_version
   *   E.g., 6, 7, or 8.
   */
  public function doSanitize($major_version) {
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
    $sql = drush_sql_get_class();
    $success = $sql->query($query);
    $output = drush_shell_exec_output();
    drush_set_context('DRUSH_SIMULATE', $current);

    return $output;
  }

}

