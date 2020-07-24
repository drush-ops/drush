# watchdog:list

Interactively filter the watchdog message listing.

#### Examples

- <code>drush watchdog:list</code>. Prompt for message type or severity, then run watchdog-show.

#### Arguments

- **substring**. A substring to look search in error messages.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --count[=COUNT]**. The number of messages to show. Defaults to 10. [default: "10"]
- ** --extended**. Return extended information about each message.
- ** --severity[=SEVERITY]**. Restrict to messages of a given severity level.
- ** --type[=TYPE]**. Restrict to messages of a given type.
- ** --fields=FIELDS**. Available fields: ID (wid), Type (type), Message (message), Severity (severity), Location (location), Hostname (hostname), Date (date), Username (username) [default: "wid,date,type,severity,message"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- wd-list
- watchdog-list

