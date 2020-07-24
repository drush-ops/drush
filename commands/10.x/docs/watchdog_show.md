# watchdog:show

Show watchdog messages.

#### Examples

- <code>drush watchdog:show</code>. Show a listing of most recent 10 messages.
- <code>drush watchdog:show "cron run succesful"</code>. Show a listing of most recent 10 messages containing the string "cron run succesful".
- <code>drush watchdog:show --count=46</code>. Show a listing of most recent 46 messages.
- <code>drush watchdog:show --severity=Notice</code>. Show a listing of most recent 10 messages with a severity of notice.
- <code>drush watchdog:show --type=php</code>. Show a listing of most recent 10 messages of type php
- <code>while sleep 2; do drush watchdog:show; done</code>. Every 2 seconds, show the most recent 10 messages.

#### Arguments

- **substring**. A substring to look search in error messages.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --count[=COUNT]**. The number of messages to show. Defaults to 10. [default: "10"]
- ** --severity=SEVERITY**. Restrict to messages of a given severity level.
- ** --type=TYPE**. Restrict to messages of a given type.
- ** --extended**. Return extended information about each message.
- ** --fields=FIELDS**. Available fields: ID (wid), Type (type), Message (message), Severity (severity), Location (location), Hostname (hostname), Date (date), Username (username) [default: "wid,date,type,severity,message"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- wd-show
- ws
- watchdog-show

