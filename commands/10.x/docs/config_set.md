# config:set

Set config value directly. Does not perform a config import.

#### Examples

- <code>drush config:set system.site page.front '/path/to/page'</code>. Sets the given URL path as value for the config item with key "page.front" of "system.site" config object.

#### Arguments

- **config_name**. The config object name, for example "system.site".
- **key**. The config key, for example "page.front".
- **value**. The value to assign to the config key. Use '-' to read from STDIN.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --input-format[=INPUT-FORMAT]**. Format to parse the object. Use "string" for string (default), and "yaml" for YAML. [default: "string"]
- ** --value=VALUE**. The value to assign to the config key (if any).

#### Aliases

- cset
- config-set

