# config:export

Export Drupal configuration to a directory.

#### Examples

- <code>drush config:export --destination</code>. Export configuration; Save files in a backup directory named config-export.

#### Arguments

- **label**. A config directory label (i.e. a key in $config_directories array in settings.php).

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --add**. Run `git add -p` after exporting. This lets you choose which config changes to sync for commit.
- ** --commit**. Run `git add -A` and `git commit` after exporting. This commits everything that was exported without prompting.
- ** --message=MESSAGE**. Commit comment for the exported configuration. Optional; may only be used with --commit.
- ** --destination[=DESTINATION]**. An arbitrary directory that should receive the exported files. A backup directory is used when no value is provided.
- ** --diff**. Show preview as a diff, instead of a change list.
- ** --format[=FORMAT]**. 

#### Aliases

- cex
- config-export

