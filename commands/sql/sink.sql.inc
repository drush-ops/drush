<?php

/*
 * Notes:
 * - Drush is required on source and dest (except with no-dump?)
 * - Source and Dest can both be remote. Convenient.
 * - No special handling for multiple targets. Very rare.
 * - We always do 'deferred sanitization'. Would be easy to move this earlier if desired.
 * - Incomplete support for source-* and target-*. Use $command_specific['sql-sync'] on the source or target alias?
 * - Stop optimizing for efficient rsync.
 *   -- Always use gzip during sql-dump. Don't default to --ordered-dump.
 *   -- Rsync destination is always a temp dir
 *   -- No 24 hour freshness check for a team of devs.
 * - Can we now simplify anything in sitealias.inc or backend.inc?
 *
 * Todo:
 *  - Cleanup temp files. @see rsync --remove-source-files
 *  -
 */

// @todo Validate inputs.

function drush_sql_sink($source, $destination) {
  // @todo Fill in placeholders.
  if (!drush_confirm(dt('You will destroy the %s database and replace with (a sanitized verion of ) %s. Do you really want to continue?'))) {
    return drush_user_abort();
  }

  $global_options = drush_redispatch_get_options() + array(
     'strict' => 0,
  );
  // Perform sql-dump on source unless told othrwise.
  $options = $global_options + array(
      'gzip' => TRUE,
      'result-file' => TRUE,
      // 'structure-tables-list' => 'cache*', // Do we want to default to this?
    );
  if (!drush_get_option('no-dump')) {
    $return = drush_invoke_process($source, 'sql-dump', array(), $options);
    if ($return['error_status']) {
      return drush_set_error('sql-dump failed.');
    }
    else {
      $dump_file = $return['object'];
    }
  }

  // Rsync file from source to destination.
  $options = $global_options + array();
  $return = drush_invoke_process($destination, 'core-rsync', array("$source:$dump_file", '@none:%temp'), $options);
  if ($return['error_status']) {
    return drush_set_error('core-rsync failed.');
  }

  // Import file into destination.
  // @todo Make sure that the %temp gets resolved to real path.
  $input_file = '%temp/' . basename($dump_file);
  $options = $global_options + array('file' => $input_file);
  $return = drush_invoke_process($destination, 'sql-query', array(), $options);
  if ($return['error_status']) {
    return drush_set_error('sql-query failed.');
  }

  // Run Sanitize if needed.
  $options = $global_options + array();
  if (drush_get_option('sanitize')) {
    $return = drush_invoke_process($destination, 'sql-sanitize', array(), $options);
    if ($return['error_status']) {
      return drush_set_error('sql-sanitize failed.');
    }
  }
}
