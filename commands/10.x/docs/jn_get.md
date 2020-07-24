# jn:get

Execute a JSONAPI request.

#### Examples

- <code>drush jn:get jsonapi/node/article</code>. Get a list of articles back as JSON.
- <code>drush jn:get jsonapi/node/article | jq</code>. Pretty print JSON by piping to jq. See https://stedolan.github.io/jq/ for lots more jq features.

#### Arguments

- **url**. The JSONAPI URL to request.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,tsv,var_dump,var_export,xml,yaml [default: "json"]
- ** --fields=FIELDS**. Limit output to only the listed elements. Name top-level elements by key, e.g. "--fields=name,date", or use dot notation to select a nested element, e.g. "--fields=a.b.c as example".
- ** --field=FIELD**. Select just one field, and force format to 'string'.

