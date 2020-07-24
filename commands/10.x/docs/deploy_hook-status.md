# deploy:hook-status

Prints information about pending deploy update hooks.

#### Examples

- <code>deploy:hook-status</code>. Prints information about pending deploy hooks.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format=FORMAT**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --fields=FIELDS**. Available fields: Module (module), Hook (hook), Description (description) [default: "module,hook,description"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:deploy`
- `drush docs:output-formats-filters`

