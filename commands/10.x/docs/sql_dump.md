# sql:dump

Exports the Drupal DB as SQL using mysqldump or equivalent.

#### Examples

- <code>drush sql:dump --result-file=../18.sql</code>. Save SQL dump to the directory above Drupal root.
- <code>drush sql:dump --skip-tables-key=common</code>. Skip standard tables. @see example.drush.yml
- <code>drush sql:dump --extra-dump=--no-data</code>. Pass extra option to mysqldump command.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --result-file=RESULT-FILE**. Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value 'auto', a date-based filename will be created under ~/drush-backups directory.
- ** --create-db**. Omit DROP TABLE statements. Used by Postgres and Oracle only.
- ** --data-only**. Dump data without statements to create any of the schema.
- ** --ordered-dump**. Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.
- ** --gzip**. Compress the dump using the gzip program which must be in your $PATH.
- ** --extra=EXTRA**. Add custom arguments/options when connecting to database (used internally to list tables).
- ** --extra-dump=EXTRA-DUMP**. Add custom arguments/options to the dumping of the database (e.g. mysqldump command).
- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,string,table,tsv,var_dump,var_export,xml,yaml [default: "null"]
- ** --fields=FIELDS**. Available fields: Path (path)
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- sql-dump

