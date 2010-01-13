<?php
// $Id$

/* TODO
 * - upgrade to specific releases.
/*

/**
 * @file
 *   Refine your Drupal major version upgrade.
 */

/**
 * Implement hook_drush_command().
 */
function upgrade_drush_command() {
  $items = array();

  $items['core-upgrade'] = array(
    'description' => "Run a major version upgrade for Drupal core and contrib modules.",
    'drupal dependencies' => array('update'),
    'arguments' => array(
      'target' => 'The name of a sitealias which points to the destination site. root, uri, and db-url keys are required. See example.drushrc.php for more information.'),
    'options' => array(
      'structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Defaults to \'common\'.',
      'source-dump' => 'Path to dump file. Medium or large sized sites should set this. Optional; default is to create a temporary file.',
    ),
    'aliases' => array('cup'),
  );
  return $items;
}

/**
 * Implement hook_drush_help().
 */
function upgrade_drush_help($section) {
  switch ($section) {
    case 'drush:core-upgrade':
      return dt("Execute a major version upgrade for Drupal core and enabled contrib modules. Command will download next version of Drupal and all available contrib modules that are available. It prepares a settings.php for the target site, and copies the prior version's database to the target site. Finally, updatedb is run. The intent is for developers to keep re-running this command until they are satisfied with the resulting site.");
  }
}

function drush_upgrade_core_upgrade_validate($target_key) {
  if (!$target_alias = drush_sitealias_get_record($target_key, TRUE)) {
    return drush_set_error('Site alias not found: @target-key. See example.drushrc.inc.', array('@target-key' => $target_key));
  }

  if (!file_exists(dirname($target_alias['root']))) {
    drush_set_error('Site alias root not found: @root. See example.drushrc.inc.', array('@root' => dirname($target_alias['root'])));
  }
}

/**
 * A drush command callback.
 */
function drush_core_upgrade($target_key) {
  // $args = func_get_args();

  $source_version = drush_drupal_major_version();
  $target_version = $source_version + 1;
  $target_alias = drush_sitealias_get_record($target_key, TRUE);
  $destination_core = $target_alias['root'];

  // Fetch target core and place as per target alias root.
  if (!file_exists($destination_core)) {
    drush_set_option('destination', dirname($destination_core));
    $new_name = trim($destination_core, '/');
    drush_set_option('drupal-project-rename', $new_name);
    // TODO: get releases other than dev snapshot.
    drush_pm_download('drupal-'. $target_version . '.x');
    if (drush_get_error()) return -1; // Early exit if we see an error.
  }

  // Get enabled projects and their paths.
  // TODO: D5 compatibility.
  module_load_include('inc', 'update', 'update.compare');
  $projects = update_get_projects();
  $projects = pm_get_project_path($projects, 'includes');

  // Fetch and place each project into target.
  // TODO: use non dev snapshot releases.
  // TODO: Fix pm-download so this cancel hack is not needed.
  drush_set_option('bootstrap_cancel', TRUE);
  foreach ($projects as $key => $project) {
    if (empty($project['path'])) {
      $project['path'] = 'sites/all/modules';
    }
    $destination_module = $destination_core . '/' . $project['path'];
    if (!file_exists($destination_module) && $key != 'drupal') {
      drush_set_option('destination', dirname($destination_module));
      drush_pm_download($key . '-'. $target_version . '.x');
    }
  }
  if (drush_get_error()) return -1; // Early exit if we see an error.

  // Create sites subdirectory in target if needed.
  $settings_source = conf_path() . '/settings.php';
  $settings_destination = $destination_core . '/' . $settings_source;
  if (!file_exists(dirname($settings_destination))) {
    if (!drush_op('mkdir', $settings_destination) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to create directory @settings_destination', array('@settings_destination' => $settings_destination)));
      return;
    }
  }

  // Copy settings.php to target.
  if (!file_exists($settings_destination)) {
    if (!drush_op('copy', $settings_source, $settings_destination) && !drush_get_context('DRUSH_SIMULATE')) {
      drush_set_error(dt('Failed to copy @source to  @dest', array('@source' => $settings_source, 'dest' => $settings_destination)));
      return;
    }
  }

  // Append new $db_url with new DB name in target's settings.php.
  drush_upgrade_fix_db_url($target_alias, $settings_destination);

  // Copy source database to target database. The source DB is not changed.
  // Always set 'common' at minimum. Sites that want other can create other key in drushrc.php.
  if (!drush_get_option('structure-tables-key')) {
    drush_set_option('structure_tables-key', 'common');
  }
  drush_include(DRUSH_BASE_PATH . '/commands/sql', 'sync.sql');
  drush_sql_sync('default', $target_key);
  if (drush_get_error()) return -1; // Early exit if we see an error.

  // Run update.php in a subshell for better isolation in case of failure.
  // TODO: leverage redispatch code once that materializes.
  // Possibly add code to spawn new subshell when memory gets 90% consumed. @see migrate module.
  $drush_script = isset($target_alias['!drush_script']) ? $target_alias['!drush_script'] : NULL;
  $remote_host = isset($target_alias['remote-host']) ? $target_alias['remote-host'] : NULL;
  drush_backend_invoke('updatedb', array('root' => $destination_core), 'GET', 'TRUE', $drush_script, $remote_host, $target_alias['root']);
}

// Replace db_url with DB name from target. updatedb will later append a DBTNG compatible version.
function drush_upgrade_fix_db_url($target_alias, $settings_destination) {
  $old_url = $GLOBALS['db_url'];
  if (is_array($old_url)) {
    $old_url = $old_url['default'];
  }
  $new_url = substr($old_url, 0, strrpos(trim($old_url), '/')) . '/'. $target_alias['databases']['default']['default']['database'];

  $append = "\n# Added by drush core-upgrade.";
  if (drush_drupal_major_version() <= 6) {
    $append .= "\n" . '$db_url = \'' . $new_url . '\';';
  }
  else {
    $databases = $GLOBALS['databases'];
    $databases['default']['default']['database'] = $target_alias['databases']['default']['default']['database'];
    $append .= "\n" . '$databases = ' . var_export($databases, TRUE) . ';';
  }
  drush_op('file_put_contents', $settings_destination, $append, FILE_APPEND);
}
