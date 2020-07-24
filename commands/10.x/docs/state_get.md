# state:get

Display a state value.

#### Examples

- <code>drush state:get system.cron_last</code>. Displays last cron run timestamp
- <code>drush state:get drupal_css_cache_files --format=yaml</code>. Displays an array of css files in yaml format.

#### Arguments

- **key**. The key name.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,string,table,tsv,var_dump,var_export,xml,yaml [default: "string"]
- ** --fields=FIELDS**. Limit output to only the listed elements. Name top-level elements by key, e.g. "--fields=name,date", or use dot notation to select a nested element, e.g. "--fields=a.b.c as example".
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Aliases

- sget
- state-get

