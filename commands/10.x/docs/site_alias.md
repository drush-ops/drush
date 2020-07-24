# site:alias

Show site alias details, or a list of available site aliases.

#### Examples

- <code>drush site:alias</code>. List all alias records known to drush.
- <code>drush site:alias @dev</code>. Print an alias record for the alias 'dev'.

#### Arguments

- **site**. Site alias or site specification.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,tsv,var_dump,var_export,xml,yaml [default: "yaml"]
- ** --fields=FIELDS**. Limit output to only the listed elements. Name top-level elements by key, e.g. "--fields=name,date", or use dot notation to select a nested element, e.g. "--fields=a.b.c as example".
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:aliases`
- `drush docs:output-formats-filters`

#### Aliases

- sa

