# config:import

Import config from a config directory.

#### Arguments

- **label**. A config directory label (i.e. a key in \$config_directories array in settings.php).

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --preview[=PREVIEW]**. Deprecated. Format for displaying proposed changes. Recognized values: list, diff. [default: "list"]
- ** --source=SOURCE**. An arbitrary directory that holds the configuration files. An alternative to label argument
- ** --partial**. Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted). No config transformation happens.
- ** --diff**. Show preview as a diff.

#### Aliases

- cim
- config-import

