# site:alias-convert

Convert legacy site alias files to the new yml format.

#### Examples

- <code>drush site:alias-convert</code>. Find legacy alias files and convert them to yml. You will be prompted for a destination directory.
- <code>drush site:alias-convert --simulate</code>. List the files to be converted but do not actually do anything.

#### Arguments

- **destination**. An absolute path to a directory for writing new alias files.If omitted, user will be prompted.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**.  [default: "yaml"]
- ** --sources=SOURCES**. A comma delimited list of paths to search. Overrides the default paths.

#### Aliases

- sa-convert
- sac

