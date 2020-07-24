# config:status

Display status of configuration (differences between the filesystem configuration and database configuration).

#### Examples

- <code>drush config:status</code>. Display configuration items that need to be synchronized.
- <code>drush config:status --state=Identical</code>. Display configuration items that are in default state.
- <code>drush config:status --state='Only in sync dir' --prefix=node.type.</code>. Display all content types that would be created in active storage on configuration import.
- <code>drush config:status --state=Any --format=list</code>. List all config names.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --state[=STATE]**. A comma-separated list of states to filter results. [default: "Only in DB,Only in sync dir,Different"]
- ** --prefix=PREFIX**. Prefix The config prefix. For example, "system". No prefix will return all names in the system.
- ** --label=LABEL**. A config directory label (i.e. a key in \$config_directories array in settings.php).
- ** --format=FORMAT**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --fields=FIELDS**. Available fields: Name (name), State (state) [default: "name,state"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- cst
- config-status

