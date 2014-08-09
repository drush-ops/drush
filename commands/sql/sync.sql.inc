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
 * Implements COMMAND hook init.
 */
function drush_sql_sync_init() {
  // Try to get @self defined when --uri was not provided.
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
}

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
    return drush_set_error('DRUSH_SOURCE_DUMP_MISSING', dt('The --source-dump option must be supplied when --no-dump is specified.'));
  }

  if (drush_get_option('no-sync') && !drush_get_option('target-dump')) {
    return drush_set_error('DRUSH_TARGET_DUMP_MISSING', dt('The --target-dump option must be supplied when --no-sync is specified.'));
  }

  if (!drush_get_context('DRUSH_SIMULATE')) {
    drush_print(dt("You will destroy data in !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
    // @todo Move sanitization prompts to here. They currently show much later.
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }
}

/*
 * A command callback.
 */
function drush_sql_sync($source, $destination) {
  // Get destination info for confirmation prompt.
  $source_settings = drush_sitealias_overlay_options(drush_sitealias_get_record($source), 'source-');
  $destination_settings = drush_sitealias_overlay_options(drush_sitealias_get_record($destination), 'target-');

  $source_is_local = !array_key_exists('remote-host', $source_settings) || drush_is_local_host($source_settings);
  $destination_is_local = !array_key_exists('remote-host', $destination_settings) || drush_is_local_host($destination_settings);

  $backend_options = array();
  $global_options = drush_redispatch_get_options() + array(
     'strict' => 0,
  );

  if (drush_get_context('DRUSH_SIMULATE')) {
    $backend_options['backend-simulate'] = TRUE;
  }

  // Create destination DB if needed.
  if (drush_get_option('create-db')) {
    drush_log(dt('Starting to create database on Destination.'), 'ok');
    $return = drush_invoke_process($destination, 'sql-create', array(), $global_options, $backend_options);
    if ($return['error_status']) {
      return drush_set_error('DRUSH_SQL_CREATE_FAILED', dt('sql-create failed.'));
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
    $return = drush_invoke_process($source, 'sql-dump', array(), $options, $backend_options);
    if ($return['error_status']) {
      return drush_set_error('DRUSH_SQL_DUMP_FAILED', dt('sql-dump failed.'));
    }
    else {
      $source_dump_path = $return['object'];
      if (!is_string($source_dump_path)) {
        return drush_set_error('DRUSH_SQL_DUMP_FILE_NOT_REPORTED', dt('The Drush sql-dump command did not report the path to the dump file produced.  Try upgrading the version of Drush you are using on the source machine.'));
      }
    }
  }
  else {
    $source_dump_path = drush_get_option('source-dump');
  }
  $do_rsync = !drush_get_option('no-sync');
  // Determine path/to/dump on destination.
  if (drush_get_option('target-dump')) {
    $destination_dump_path = drush_get_option('target-dump');
    $rsync_options['yes'] = TRUE;  // @temporary: See https://github.com/drush-ops/drush/pull/555
  }
  elseif ($source_is_local && $destination_is_local) {
    $destination_dump_path = $source_dump_path;
    $do_rsync = false;
  }
  else {
    $tmp = '/tmp'; // Our fallback plan.
    drush_log(dt('Starting to discover temporary files directory on Destination.'), 'ok');
    $return = drush_invoke_process($destination, 'php-eval', array('return drush_find_tmp();'), array(), array('integrate' => FALSE, 'override-simulated' => TRUE));
    if (!$return['error_status']) {
      $tmp = $return['object'];
    }
    $destination_dump_path = $tmp . '/' . basename($source_dump_path);
    $rsync_options['yes'] = TRUE;  // No need to prompt as destination is a tmp file.
  }
  if ($do_rsync) {
    if (!drush_get_option('no-dump')) {
      // Cleanup if this command created the dump file.
      $rsync_options['remove-source-files'] = TRUE;
    }

    // Try run to rsync locally so that aliases always resolve. https://github.com/drush-ops/drush/issues/668
    if (drush_sitealias_is_remote_site($source) === FALSE) {
      $runner = $source;
    }
    elseif (drush_sitealias_is_remote_site($destination) === FALSE) {
      $runner = $destination;
    }
    else {
      // Both are remote. Arbitrarily run rsync on destination. Aliases must be defined there (for now).
      // @todo Add an option for choosing runner? Resolve aliases before rsync?
      $runner = $destination;
    }
    $return = drush_invoke_process($runner, 'core-rsync', array("$source:$source_dump_path", "$destination:$destination_dump_path"), $rsync_options);
    drush_log(dt('Copying dump file from Source to Destination.'), 'ok');
    if ($return['error_status']) {
      return drush_set_error('DRUSH_RSYNC_FAILED', dt('core-rsync failed.'));
    }
  }

  // Import file into destination.
  drush_log(dt('Starting to import dump file onto Destination database.'), 'ok');
  $options = $global_options + array(
    'file' => $destination_dump_path,
    'file-delete' => TRUE,
  );
  $return = drush_invoke_process($destination, 'sql-query', array(), $options, $backend_options);
  if ($return['error_status']) {
    // An error was already logged.
    return FALSE;
  }

  // Run Sanitize if needed.
  $options = $global_options + array();
  if (drush_get_option('sanitize')) {
    drush_log(dt('Starting to sanitize target database on Destination.'), 'ok');
    $return = drush_invoke_process($destination, 'sql-sanitize', array(), $options, $backend_options);
    if ($return['error_status']) {
      return drush_set_error('DRUSH_SQL_SANITIZE_FAILED', dt('sql-sanitize failed.'));
    }
  }
}
