# sql:cli

Open a SQL command-line interface using Drupal's credentials.

#### Examples

- <code>drush sql:cli</code>. Open a SQL command-line interface using Drupal's credentials.
- <code>drush sql:cli --extra=--progress-reports</code>. Open a SQL CLI and skip reading table information.
- <code>drush sql:cli < example.sql</code>. Import sql statements from a file into the current database.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --extra=EXTRA**. Add custom options to the connect string

#### Aliases

- sqlc
- sql-cli

