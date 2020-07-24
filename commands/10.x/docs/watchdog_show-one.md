# watchdog:show-one

Show one log record by ID.

#### Arguments

- **id**. Watchdog Id

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,string,table,tsv,var_dump,var_export,xml,yaml [default: "yaml"]
- ** --fields=FIELDS**. Limit output to only the listed elements. Name top-level elements by key, e.g. "--fields=name,date", or use dot notation to select a nested element, e.g. "--fields=a.b.c as example".
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Aliases

- wd-one
- watchdog-show-one

