# pm:list

Show a list of available extensions (modules and themes).

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --type[=TYPE]**. Only show extensions having a given type. Choices: module, theme. [default: "module,theme"]
- ** --status[=STATUS]**. Only show extensions having a given status. Choices: enabled or disabled. [default: "enabled,disabled"]
- ** --package=PACKAGE**. Only show extensions having a given project packages (e.g. Development).
- ** --core**. Only show extensions that are in Drupal core.
- ** --no-core**. Only show extensions that are not provided by Drupal core.
- ** --fields=FIELDS**. Available fields: Package (package), Name (display_name), Name (name), Type (type), Path (path), Status (status), Version (version) [default: "package,display_name,status,version"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- pml
- pm-list

