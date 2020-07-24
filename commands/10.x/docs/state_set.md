# state:set

Set a state value.

#### Examples

- <code>drush sset system.maintenance_mode 1 --input-format=integer</code>. Put site into Maintenance mode.
- <code>drush state:set system.cron_last 1406682882 --input-format=integer</code>. Sets a timestamp for last cron run.
- <code>php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state-set --input-format=json foo.name -</code>. Set a key to a complex value (e.g. array)

#### Arguments

- **key**. The state key, for example: system.cron_last.
- **value**. The value to assign to the state key. Use '-' to read from STDIN.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --input-format[=INPUT-FORMAT]**. Type for the value. Defaults to 'auto'. Other recognized values: string, integer, float, boolean, json, yaml. [default: "auto"]
- ** --value=VALUE**. For internal use only.

#### Aliases

- sset
- state-set

