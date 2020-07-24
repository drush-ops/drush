# updatedb:status

List any pending database updates.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --entity-updates[=ENTITY-UPDATES]**. Show entity schema updates. [default: "1"]
- ** --post-updates[=POST-UPDATES]**. Show post updates. [default: "1"]
- ** --no-entity-updates**. Negate --entity-updates option.
- ** --no-post-updates**. Negate --post-updates option.
- ** --fields=FIELDS**. Available fields: Module (module), Update ID (update_id), Description (description), Type (type) [default: "module,update_id,type,description"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- updbst
- updatedb-status

