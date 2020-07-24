# sql:sync

Copy DB data from a source site to a target site. Transfers data via rsync.

#### Examples

- <code>drush sql:sync @source @self</code>. Copy the database from the site with the alias 'source' to the local site.
- <code>drush sql:sync @self @target</code>. Copy the database from the local site to the site with the alias 'target'.
- <code>drush sql:sync #prod #dev</code>. Copy the database from the site in /sites/prod to the site in /sites/dev (multisite installation).

#### Arguments

- **source**. A site-alias or the name of a subdirectory within /sites whose database you want to copy from.
- **target**. A site-alias or the name of a subdirectory within /sites whose database you want to replace.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --no-dump**. Do not dump the sql database; always use an existing dump file.
- ** --no-sync**. Do not rsync the database dump file from source to target.
- ** --runner=RUNNER**. Where to run the rsync command; defaults to the local site. Can also be 'source' or 'target'.
- ** --create-db**. Create a new database before importing the database dump on the target machine.
- ** --db-su=DB-SU**. Account to use when creating a new database (e.g. root).
- ** --db-su-pw=DB-SU-PW**. Password for the db-su account.
- ** --target-dump=TARGET-DUMP**. The path for storing the sql-dump on target machine.
- ** --source-dump[=SOURCE-DUMP]**. The path for retrieving the sql-dump on source machine.
- ** --extra-dump[=EXTRA-DUMP]**. Add custom arguments/options to the dumping of the database (e.g. mysqldump command).

#### Topics

- `drush docs:aliases`
- `drush docs:policy`
- `drush docs:configuration`
- `drush docs:example-sync-via-http`

#### Aliases

- sql-sync

