<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class SqlCommands extends DrushCommands {

  /**
   * Print database connection details using print_r().
   *
   * @command sql-conf
   * @option all Show all database connections, instead of just one.
   * @option show-passwords Show database password.
   * @optionset_sql
   * @hidden
   */
  public function conf($options = ['format' => 'yaml', 'all' => FALSE, 'show-passwords' => FALSE]) {
    drush_sql_bootstrap_database_configuration();
    if ($options['all']) {
      $sqlVersion = drush_sql_get_version();
      $return = $sqlVersion->getAll();
      foreach ($return as $key1 => $value) {
        foreach ($value as $key2 => $spec) {
          if (!$options['show-passwords']) {
            unset($return[$key1][$key2]['password']);
          }
        }
      }
    }
    else {
      $sql = drush_sql_get_class();
      $return = $sql->db_spec();
      if (!$options['show-passwords']) {
        unset($return['password']);
      }
    }
    return $return;
  }

  /**
   * A string for connecting to the DB.
   *
   * @command sql-connect
   * @option extra Add custom options to the connect string (e.g. --extra=--skip-column-names)
   * @optionset_sql
   * @usage `drush sql-connect` < example.sql
   *   Bash: Import SQL statements from a file into the current database.
   * @usage eval (drush sql-connect) < example.sql
   *   Fish: Import SQL statements from a file into the current database.
   */
  public function connect($options = ['extra' => '']) {
    drush_sql_bootstrap_further();
    $sql = drush_sql_get_class();
    return $sql->connect(FALSE);
  }

  /**
   * Create a database.
   *
   * @command sql-create
   * @option db-su Account to use when creating a new database.
   * @option db-su-pw Password for the db-su account.
   * @optionset_sql
   * @usage drush sql-create
   *   Create the database for the current site.
   * @usage drush @site.test sql-create
   *   Create the database as specified for @site.test.
   * @usage drush sql-create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"
   *   Create the database as specified in the db-url option.
   */
  public function create() {
    drush_sql_bootstrap_further();
    $sql = drush_sql_get_class();
    $db_spec = $sql->db_spec();
    // Prompt for confirmation.
    if (!drush_get_context('DRUSH_SIMULATE')) {
      // @todo odd - maybe for sql-sync.
      $txt_destination = (isset($db_spec['remote-host']) ? $db_spec['remote-host'] . '/' : '') . $db_spec['database'];
      drush_print(dt("Creating database !target. Any possible existing database will be dropped!", array('!target' => $txt_destination)));

      if (!drush_confirm(dt('Do you really want to continue?'))) {
        return drush_user_abort();
      }
    }

    return $sql->createdb(TRUE);
  }

  /**
   * Drop all tables in a given database.
   *
   * @command sql-drop
   * @option result-file  Save to a file. Value should be relative to Drupal root.
   * @optionset_sql
   * @topics docs-policy
   */
  public function drop() {
    drush_sql_bootstrap_further();
    $sql = drush_sql_get_class();
    $db_spec = $sql->db_spec();
    if (!drush_confirm(dt('Do you really want to drop all tables in the database !db?', array('!db' => $db_spec['database'])))) {
      return drush_user_abort();
    }
    $tables = $sql->listTables();
    return $sql->drop($tables);
  }

  /**
   * Open a SQL command-line interface using Drupal's credentials.
   *
   * @command sql-cli
   * @optionset_sql
   * @allow-additional-options sql-connect
   * @aliases sqlc
   * @usage drush sql-cli
   *   Open a SQL command-line interface using Drupal's credentials.
   * @usage drush sql-cli --extra=-A
   *   Open a SQL CLI and skip reading table information.
   * @remote-tty
   */
  public function cli() {
    drush_sql_bootstrap_further();
    $sql = drush_sql_get_class();
    drush_shell_proc_open($sql->connect());
  }

  /**
   * Execute a query against a database.
   *
   * @command sql-query
   * @param $query An SQL query. Ignored if --file is provided.
   * @optionset_sql
   * @option result-file Save to a file. The file should be relative to Drupal root.
   * @option file Path to a file containing the SQL to be run. Gzip files are accepted.
   * @option extra Add custom options to the connect string (e.g. --extra=--skip-column-names)
   * @option db-prefix Enable replacement of braces in your query.
   * @option db-spec A database specification. Only used with --backend calls.
   * @hidden-option db-spec
   * @aliases sqlq
   * @usage drush sql-query "SELECT * FROM users WHERE uid=1"
   *   Browse user record. Table prefixes, if used, must be added to table names by hand.
   * @usage drush sql-query --db-prefix "SELECT * FROM {users} WHERE uid=1"
   *   Browse user record. Table prefixes are honored.  Caution: curly-braces will be stripped from all portions of the query.
   * @usage `drush sql-connect` < example.sql
   *   Import sql statements from a file into the current database.
   * @usage drush sql-query --file=example.sql
   *   Alternate way to import sql statements from a file.
   *
   */
  public function query($query = '', $options = ['result-file' => NULL, 'file' => NULL, 'extra' => NULL, 'db-prefix' => NULL, 'db-spec' => NULL]) {
    drush_sql_bootstrap_further();
    $filename = $options['file'];
    // Enable prefix processing when db-prefix option is used.
    if ($options['db-prefix']) {
      drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
    }
    if (drush_get_context('DRUSH_SIMULATE')) {
      if ($query) {
        drush_print(dt('Simulating sql-query: !q', array('!q' => $query)));
      }
      else {
        drush_print(dt('Simulating sql-import from !f', array('!f' => $options['file'])));
      }
    }
    else {
      $sql = drush_sql_get_class($options['db-spec']);
      $result = $sql->query($query, $filename, $options['result-file']);
      if (!$result) {
        return drush_set_error('DRUSH_SQL_NO_QUERY', dt('Query failed.'));
      }
      drush_print(implode("\n", drush_shell_exec_output()));
    }
    return TRUE;
  }

  /**
   * Exports the Drupal DB as SQL using mysqldump or equivalent.
   *
   * @command sql-dump
   * @optionset_sql
   * @optionset_table_selection
   * @option result-file Save to a file. The file should be relative to Drupal root.
   * @option create-db Omit DROP TABLE statements. Postgres and Oracle only.  Used by sql-sync, since including the DROP TABLE statements interfere with the import when the database is created.
   * @option data-only Dump data without statements to create any of the schema.
   * @option ordered-dump Order by primary key and add line breaks for efficient diff in revision control. Slows down the dump. Mysql only.
   * @option gzip Compress the dump using the gzip program which must be in your $PATH.
   * @option extra Add custom options to the dump command.
   * @usage drush sql-dump --result-file=../18.sql
   *   Save SQL dump to the directory above Drupal root.
   * @usage drush sql-dump --skip-tables-key=common
   *   Skip standard tables. @see example.drushrc.php
   * @usage drush sql-dump --extra=--no-data
   *   Pass extra option to dump command.
   * @hidden-option create-db
   */
  public function dump($options = ['result-file' => NULL, 'create-db' => NULL, 'data-only' => NULL, 'ordered-dump' => NULL, 'gzip' => NULL, 'extra' => NULL]) {
    drush_sql_bootstrap_further();
    $sql = drush_sql_get_class();
    return $sql->dump($options['result-file']);
  }
}