<?php
use Drush\Sql\SqlBase;

/**
 * These are deprecated functions that we keep around just to ease the transition
 * to current Drush version. They may not work the same as they did in prior
 * versions. If they aren't working for you, consider upgrading your commandfile
 * so that it works with modern Drush.
 */

function drush_sql_bootstrap_further() {
  
}

function drush_sql_get_class($db_spec = NULL) {
  $options = [];
  if ($url = drush_get_option('db-url')) {
    $options['db-url'] = $url;
  }
  return SqlBase::create($options);
}

/**
 * Given an alias record, overwrite its values with options
 * from the command line and other drush contexts as specified
 * by the provided prefix.  For example, if the prefix is 'source-',
 * then any option 'source-foo' will set the value 'foo' in the
 * alias record.
 */
function drush_sitealias_overlay_options($site_alias_record, $prefix) {
  return array_merge($site_alias_record, drush_get_merged_prefixed_options($prefix));
}

/**
 * Retrieves a collapsed list of all options
 * with a specified prefix.
 */
function drush_get_merged_prefixed_options($prefix) {
  $merged_options = drush_get_merged_options();
  $result = array();
  foreach ($merged_options as $key => $value) {
    if ($prefix == substr($key, 0, strlen($prefix))) {
      $result[substr($key, strlen($prefix))] = $value;
    }
  }

  return $result;
}
