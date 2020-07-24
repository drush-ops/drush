# sql:query

Execute a query against a database.

#### Examples

- <code>drush sql:query "SELECT * FROM users WHERE uid=1"</code>. Browse user record. Table prefixes, if used, must be added to table names by hand.
- <code>drush sql:query --db-prefix "SELECT * FROM {users}"</code>. Browse user record. Table prefixes are honored. Caution: All curly-braces will be stripped.
- <code>`drush sql-connect` < example.sql</code>. Import sql statements from a file into the current database.
- <code>drush sql:query --file=example.sql</code>. Alternate way to import sql statements from a file.
- <code>drush @d8 ev "return db_query('SELECT * FROM users')->fetchAll()" --format=json</code>. Get data back in JSON format. See https://github.com/drush-ops/drush/issues/3071#issuecomment-347929777.

#### Arguments

- **query**. An SQL query. Ignored if --file is provided.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --result-file[=RESULT-FILE]**. Save to a file. The file should be relative to Drupal root.
- ** --file=FILE**. Path to a file containing the SQL to be run. Gzip files are accepted.
- ** --file-delete**. Delete the --file after running it.
- ** --extra=EXTRA**. Add custom options to the connect string (e.g. --extra=--skip-column-names)
- ** --db-prefix**. Enable replacement of braces in your query.

#### Aliases

- sqlq
- sql-query

