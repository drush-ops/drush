# sql:connect

A string for connecting to the DB.

#### Examples

- <code>`drush sql-connect` < example.sql</code>. Bash: Import SQL statements from a file into the current database.
- <code>eval (drush sql-connect) < example.sql</code>. Fish: Import SQL statements from a file into the current database.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --extra=EXTRA**. Add custom options to the connect string (e.g. --extra=--skip-column-names)

#### Aliases

- sql-connect

