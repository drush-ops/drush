# config:pull

Export and transfer config from one environment to another.

#### Examples

- <code>drush config:pull @prod @stage</code>. Export config from @prod and transfer to @stage.
- <code>drush config:pull @prod @self --label=vcs</code>. Export config from @prod and transfer to the 'vcs' config directory of current site.
- <code>drush config:pull @prod @self:../config/sync</code>. Export config to a custom directory. Relative paths are calculated from Drupal root.

#### Arguments

- **source**. A site-alias or the name of a subdirectory within /sites whose config you want to copy from.
- **destination**. A site-alias or the name of a subdirectory within /sites whose config you want to replace.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --safe**. Validate that there are no git uncommitted changes before proceeding
- ** --label[=LABEL]**. A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync' [default: "sync"]
- ** --runner[=RUNNER]**. Where to run the rsync command; defaults to the local site. Can also be 'source' or 'destination'
- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,string,table,tsv,var_dump,var_export,xml,yaml [default: "null"]
- ** --fields=FIELDS**. Available fields: Path (path)
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:aliases`
- `drush docs:config:exporting`
- `drush docs:output-formats-filters`

#### Aliases

- cpull
- config-pull

