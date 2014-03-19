<?php

/*
 * Notes:
 * - Drush is required on source and dest (except with --no-dump)
 * - Source and Dest can both be remote. Convenient.
 * - No special handling for multiple targets. Use --no-dump.
 * - We could move the sanitize confirmations earlier if desired.
 * - A bit tricky to know where to put your drushrc/alias customizations. Can be local, $source, or $destination. Just add docs?
 * - No longer optimizing for efficient rsync. We could add this back if desired.
 *   -- Always use gzip during sql-dump. Don't use --ordered-dump.
 *   -- No more 24-hour freshness check for a team of devs.
 * - Can we now simplify anything in sitealias.inc or backend.inc?
 */

/*
 * A command validate callback.
 */
function drush_sql_sync_validate($source, $destination) {
  // Get destination info for confirmation prompt.
  $source_settings = drush_sitealias_overlay_options(drush_sitealias_get_record($source), 'source-');
  $destination_settings = drush_sitealias_overlay_options(drush_sitealias_get_record($destination), 'target-');
  $source_db_url = drush_sitealias_get_db_spec($source_settings, FALSE, 'source-');
  $target_db_url = drush_sitealias_get_db_spec($destination_settings, FALSE, 'target-');
  $txt_source = (isset($source_db_url['remote-host']) ? $source_db_url['remote-host'] . '/' : '') . $source_db_url['database'];
  $txt_destination = (isset($target_db_url['remote-host']) ? $target_db_url['remote-host'] . '/' : '') . $target_db_url['database'];
  // Validate.
  if (empty($source_db_url)) {
    if (empty($source_settings)) {
      return drush_set_error('DRUSH_ALIAS_NOT_FOUND', dt('Error: no alias record could be found for source !source', array('!source' => $source)));
    }
    return drush_set_error('DRUSH_DATABASE_NOT_FOUND', dt('Error: no database record could be found for source !source', array('!source' => $source)));
  }
  if (empty($target_db_url)) {
    if (empty($destination_settings)) {
      return drush_set_error('DRUSH_ALIAS_NOT_FOUND', dt('Error: no alias record could be found for target !destination', array('!destination' => $destination)));
    }
    return drush_set_error('DRUSH_DATABASE_NOT_FOUND', dt('Error: no database record could be found for target !destination', array('!destination' => $destination)));
  }
  if (isset($source_db_url['remote-host']) && isset($target_db_url['remote-host']) && ($source_db_url['remote-host'] == $target_db_url['remote-host']) && ($source_db_url['database'] == $target_db_url['database']) && !drush_get_context('DRUSH_SIMULATE')) {
    return drush_set_error('DRUSH_SAME_DATABASE', dt('Source and target databases are the same; please sync to a different target.'));
  }

  if (drush_get_option('no-dump') && !drush_get_option('source-dump')) {
    return drush_set_error(DRUSH_SOURCE_DUMP_MISSING, dt('The --source-dump option must be supplied when --no-dump is specified.'));
  }

  if (drush_get_option('no-sync') && !drush_get_option('target-dump')) {
    return drush_set_error(DRUSH_TARGET_DUMP_MISSING, dt('The --target-dump option must be supplied when --no-sync is specified.'));
  }

  drush_print(dt("You will destroy data in !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
  // @todo Move sanitization prompts to here. They currently show much later.
  if (!drush_confirm(dt('Do you really want to continue?'))) {
    return drush_user_abort();
  }
}

/*
 * A command callback.
 */
function drush_sql_sync($source, $destination) {
  $global_options = drush_redispatch_get_options() + array(
     'strict' => 0,
  );

  // Create destination DB if needed.
  if (drush_get_option('create-db')) {
    drush_log(dt('Starting to create database on Destination.'), 'ok');
    $return = drush_invoke_process($destination, 'sql-create', array(), $global_options);
    if ($return['error_status']) {
      return drush_set_error('sql-create failed.');
    }
  }

  // Perform sql-dump on source unless told othrwise.
  $options = $global_options + array(
      'gzip' => TRUE,
      'result-file' => drush_get_option('source-dump', TRUE),
      // 'structure-tables-list' => 'cache*', // Do we want to default to this?
    );
  if (!drush_get_option('no-dump')) {
    drush_log(dt('Starting to dump database on Source.'), 'ok');
    $return = drush_invoke_process($source, 'sql-dump', array(), $options);
    if ($return['error_status']) {
      return drush_set_error('sql-dump failed.');
    }
    else {
      $source_dump_path = $return['object'];
    }
  }
  else {
    $source_dump_path = drush_get_option('source-dump');
  }

  // Determine path/to/dump on destination.
  if (drush_get_option('target-dump')) {
    $destination_dump_path = drush_get_option('target-dump');
  }
  else {
    $tmp = '/tmp'; // Our fallback plan.
    drush_log(dt('Starting to discover temporary files directory on Destination.'), 'ok');
    $return = drush_invoke_process($destination, 'php-eval', array('return drush_find_tmp();'), array(), array('integrate' => FALSE, 'override-simulated' => TRUE));
    if (!$return['error_status']) {
      $tmp = $return['object'];
    }
    $destination_dump_path = $tmp . '/' . basename($source_dump_path);
  }

  if (!drush_get_option('no-sync')) {
    drush_log(dt('Copying dump file from Source to Destination.'), 'ok');
    // Don't send redispatch options to rsync as they get appended after core-rsync with sad outcome.
    $options = array();
    if (!drush_get_option('no-dump')) {
      // Cleanup if this command created the dump file.
      $options = array('remove-source-files' => TRUE);
    }
    $return = drush_invoke_process($destination, 'core-rsync', array("$source:$source_dump_path", "@self:$destination_dump_path"), $options);
    if ($return['error_status']) {
      return drush_set_error('core-rsync failed.');
    }
  }

  // Import file into destination.
  drush_log(dt('Starting to import dump file onto Destination database.'), 'ok');
  $options = $global_options + array(
    'file' => $destination_dump_path,
    'file-delete' => TRUE,
  );
  $return = drush_invoke_process($destination, 'sql-query', array(), $options);
  if ($return['error_status']) {
    // An error was already logged.
    return FALSE;
  }

  // Run Sanitize if needed.
  $options = $global_options + array();
  if (drush_get_option('sanitize')) {
    drush_log(dt('Starting to sanitize target database on Destination.'), 'ok');
    $return = drush_invoke_process($destination, 'sql-sanitize', array(), $options);
    if ($return['error_status']) {
      return drush_set_error('sql-sanitize failed.');
    }
  }
}
