# config:get

Display a config value, or a whole configuration object.

#### Examples

- <code>drush config:get system.site</code>. Displays the system.site config.
- <code>drush config:get system.site page.front</code>. Gets system.site:page.front value.

#### Arguments

- **config_name**. The config object name, for example "system.site".
- **key**. The config key, for example "page.front". Optional.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**.  [default: "yaml"]
- ** --source[=SOURCE]**. The config storage source to read. Additional labels may be defined in settings.php. [default: "active"]
- ** --include-overridden**. Apply module and settings.php overrides to values.

#### Aliases

- cget
- config-get

