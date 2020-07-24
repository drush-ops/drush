# twig:unused

Find potentially unused Twig templates.

Immediately before running this command, crawl your entire web site. Or
use your Production PHPStorage dir for comparison.

#### Examples

- <code>drush twig:unused --field=template /var/www/mass.local/docroot/modules/custom,/var/www/mass.local/docroot/themes/custom</code>. Output a simple list of potentially unused templates.

#### Arguments

- **searchpaths**. A comma delimited list of paths to recursively search

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format=FORMAT**. Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml [default: "table"]
- ** --fields=FIELDS**. Available fields: Template (template), Compiled (compiled) [default: "template,compiled"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

