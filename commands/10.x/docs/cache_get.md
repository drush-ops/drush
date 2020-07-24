# cache:get

Fetch a cached object and display it.

#### Examples

- <code>drush cache:get hook_info bootstrap</code>. Display the data for the cache id "hook_info" from the "bootstrap" bin.
- <code>drush cache:get update_available_releases update</code>. Display the data for the cache id "update_available_releases" from the "update" bin.

#### Arguments

- **cid**. The id of the object to fetch.
- **bin**. The cache bin to fetch from.

#### Options

!!! note "Tip"

    An option value without square brackets is mandatory. Any default value is listed at description end.

- ** --format[=FORMAT]**. Format the result data. Available formats: csv,json,list,null,php,print-r,string,table,tsv,var_dump,var_export,xml,yaml [default: "json"]
- ** --fields=FIELDS**. Available fields: Cache ID (cid), Data (data), Created (created), Expire (expire), Tags (tags), Checksum (checksum), Valid (valid) [default: "cid,data,created,expire,tags"]
- ** --field=FIELD**. Select just one field, and force format to 'string'.

#### Topics

- `drush docs:output-formats-filters`

#### Aliases

- cg
- cache-get

