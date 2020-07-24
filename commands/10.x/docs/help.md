# help

Display usage details for a command.

#### Examples

- <code>drush help pm-uninstall</code>. Show help for a command.
- <code>drush help pmu</code>. Show help for a command using an alias.
- <code>drush help --format=xml</code>. Show all available commands in XML format.
- <code>drush help --format=json</code>. All available commands, in JSON format.

#### Arguments

- **command_name**. A command name

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,string,tsv,var_dump,var_export,xml,yaml [default: "helpcli"]
- ** --include-field-labels**. 
- ** --table-style[=TABLE-STYLE]**.  [default: "compact"]

#### Topics

- `drush docs:readme`

