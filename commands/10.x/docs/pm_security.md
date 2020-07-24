# pm:security

Check Drupal Composer packages for pending security updates.

This uses the Drupal security advisories package to determine if updates
are available.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format=FORMAT**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --fields=FIELDS**. Available fields: Name (name), Installed Version (version) [default: "name,version"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- sec
- pm-security

