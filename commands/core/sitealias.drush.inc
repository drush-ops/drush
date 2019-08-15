<?php

/**
 * @file
 *   Site alias commands. @see example.drushrc.php for details.
 */

function sitealias_drush_help($section) {
  switch ($section) {
    case 'drush:site-alias':
      return dt('Print an alias record.');
  }
}

function sitealias_drush_command() {
  $items = array();

  $items['site-alias'] = array(
    'callback' => 'drush_sitealias_print',
    'description' => 'Print site alias records for all known site aliases and local sites.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'arguments' => array(
      'site' => 'Site specification to print',
    ),
    'options' => array(
      'with-db' => 'Include the databases structure in the full alias record.',
      'with-db-url' => 'Include the short-form db-url in the full alias record.',
      'no-db' => 'Do not include the database record in the full alias record (default).',
      'with-optional' => 'Include optional default items.',
      'alias-name' => 'For a single alias, set the name to use in the output.',
      'local-only' => 'Only display sites that are available on the local system (remote-site not set, and Drupal root exists).',
      'show-hidden' => 'Include hidden internal elements in site alias output',
    ),
    'outputformat' => array(
      'default' => 'config',
      'pipe-format' => 'var_export',
      'variable-name' => 'aliases',
      'hide-empty-fields' => TRUE,
      'private-fields' => 'password',
      'field-labels' => array('#name' => 'Name', 'root' => 'Root', 'uri' => 'URI', 'remote-host' => 'Host', 'remote-user' => 'User', 'remote-port' => 'Port', 'os' => 'OS', 'ssh-options' => 'SSH options', 'php' => 'PHP'),
      'fields-default' => array('#name', 'root', 'uri', 'remote-host', 'remote-user'),
      'field-mappings' => array('name' => '#name'),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('sa', 'site:alias'),
    'examples' => array(
      'drush site-alias' => 'List all alias records known to drush.',
      'drush site-alias @dev' => 'Print an alias record for the alias \'dev\'.',
      'drush @none site-alias' => 'Print only actual aliases; omit multisites from the local Drupal installation.',
    ),
    'topics' => array('docs-aliases'),
  );
  $items['site-set'] = array(
    'description' => 'Set a site alias to work on that will persist for the current session.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'handle-remote-commands' => TRUE,
    'arguments' => array(
      'site' => 'Site specification to use, or "-" for previous site. Omit this argument to "unset"',
    ),
    'aliases' => array('use', 'site:set'),
    'examples' => array(
      'drush site-set @dev' => 'Set the current session to use the @dev alias.',
      'drush site-set user@server/path/to/drupal#sitename' => 'Set the current session to use a remote site via site specification.',
      'drush site-set /path/to/drupal#sitename' => 'Set the current session to use a local site via site specification.',
      'drush site-set -' => 'Go back to the previously-set site (like `cd -`).',
      'drush site-set' => 'Without an argument, any existing site becomes unset.',
    ),
  );
  return $items;
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of available site aliases.
 */
function sitealias_site_alias_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of available site aliases.
 */
function sitealias_site_set_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Return a list of all site aliases known to drush.
 *
 * The array key is the site alias name, and the array value
 * is the site specification for the given alias.
 */
function _drush_sitealias_alias_list() {
  return drush_get_context('site-aliases');
}

/**
 * Return a list of all of the local sites at the current drupal root.
 *
 * The array key is the site folder name, and the array value
 * is the site specification for that site.
 */
function _drush_sitealias_site_list() {
  $site_list = array();
  $base_path = drush_get_context('DRUSH_DRUPAL_ROOT');
  if ($base_path) {
    $base_path .= '/sites';
    $files = drush_scan_directory($base_path, '/settings\.php/', array('.', '..', 'CVS', 'all'), 0, 1);
    foreach ($files as $filename => $info) {
      if ($info->basename == 'settings.php') {
        $alias_record = drush_sitealias_build_record_from_settings_file($filename);
        if (!empty($alias_record)) {
          $site_list[drush_sitealias_uri_to_site_dir($alias_record['uri'])] = $alias_record;
        }
      }
    }
  }
  return $site_list;
}

/**
 * Return the list of all site aliases and all local sites.
 */
function _drush_sitealias_all_list() {
  drush_sitealias_load_all();
  return array_merge(_drush_sitealias_alias_list(), _drush_sitealias_site_list());
}

/**
 * Return the list of site aliases (remote or local) that the
 * user specified on the command line.  If none were specified,
 * then all are returned.
 */
function _drush_sitealias_user_specified_list() {
  $command = drush_get_command();
  $specifications = $command['arguments'];
  $site_list = array();

  // Iterate over the arguments and convert them to alias records
  if (!empty($specifications)) {
    list($site_list, $not_found) = drush_sitealias_resolve_sitespecs($specifications);
    if (!empty($not_found)) {
      return drush_set_error('DRUSH_ALIAS_NOT_FOUND', dt("Not found: @list", array("@list" => implode(', ', $not_found))));
    }
  }
  // If the user provided no args, then we will return everything.
  else {
    drush_set_default_outputformat('list');
    $site_list = _drush_sitealias_all_list();

    // Filter out the hidden items
    foreach ($site_list as $site_name => $one_site) {
      if (array_key_exists('#hidden', $one_site)) {
        unset($site_list[$site_name]);
      }
    }
  }

  // Filter for only local sites if specified.
  if (drush_get_option('local-only', FALSE)) {
    foreach ($site_list as $site_name => $one_site) {
      if ( (array_key_exists('remote-site', $one_site)) ||
           (!array_key_exists('root', $one_site)) ||
           (!is_dir($one_site['root']))
         ) {
        unset($site_list[$site_name]);
      }
    }
  }
  return $site_list;
}

/**
 * Print out the specified site aliases (or else all) using the format
 * specified.
 */
function drush_sitealias_print() {
  // Try to get the @self alias to be defined.
  $phase = drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  $site_list = _drush_sitealias_user_specified_list();
  if ($site_list === FALSE) {
    return FALSE;
  }
  ksort($site_list);
  $with_db = (drush_get_option('with-db') != NULL) || (drush_get_option('with-db-url') != NULL);

  $site_specs = array();
  foreach ($site_list as $site => $alias_record) {
    $result_record = _drush_sitealias_prepare_record($alias_record);
    $site_specs[$site] = $result_record;
  }
  ksort($site_specs);
  return $site_specs;
}

/**
 * Given a site alias name, print out a php-syntax
 * representation of it.
 *
 * @param alias_record
 *   The name of the site alias to print
 */
function _drush_sitealias_prepare_record($alias_record) {
  $output_db = drush_get_option('with-db');
  $output_db_url = drush_get_option('with-db-url');
  $output_optional_items = drush_get_option('with-optional');

  // Make sure that the default items have been added for all aliases
  _drush_sitealias_add_static_defaults($alias_record);

  // Include the optional items, if requested
  if ($output_optional_items) {
    _drush_sitealias_add_transient_defaults($alias_record);
  }

  drush_sitealias_resolve_path_references($alias_record);

  if (isset($output_db_url) || isset($output_db)) {
    drush_sitealias_add_db_settings($alias_record);
  }
  // If the user specified --with-db-url, then leave the
  // 'db-url' entry in the alias record (unless it is not
  // set, in which case we will leave the 'databases' record instead).
  if (isset($output_db_url)) {
    if (!isset($alias_record['db-url'])) {
      $alias_record['db-url'] = drush_sitealias_convert_databases_to_db_url($alias_record['databases']);
    }
    unset($alias_record['databases']);
  }
  // If the user specified --with-db, then leave the
  // 'databases' entry in the alias record.
  else if (isset($output_db)) {
    unset($alias_record['db-url']);
  }
  // If neither --with-db nor --with-db-url were specified,
  // then remove both the 'db-url' and the 'databases' entries.
  else {
    unset($alias_record['db-url']);
    unset($alias_record['databases']);
  }

  // We don't want certain fields to go into the output
  if (!drush_get_option('show-hidden')) {
    foreach ($alias_record as $key => $value) {
      if ($key[0] == '#') {
        unset($alias_record[$key]);
      }
    }
  }

  // We only want to output the 'root' item; don't output the '%root' path alias
  if (array_key_exists('path-aliases', $alias_record) && array_key_exists('%root', $alias_record['path-aliases'])) {
    unset($alias_record['path-aliases']['%root']);
    // If there is nothing left in path-aliases, then clear it out
    if (count($alias_record['path-aliases']) == 0) {
      unset($alias_record['path-aliases']);
    }
  }

  return $alias_record;
}

function _drush_sitealias_print_record($alias_record, $site_alias = '') {
  $result_record = _drush_sitealias_prepare_record($alias_record);

  // The alias name will be the same as the site alias name,
  // unless the user specified some other name on the command line.
  $alias_name = drush_get_option('alias-name');
  if (!isset($alias_name)) {
    $alias_name = $site_alias;
    if (empty($alias_name) || is_numeric($alias_name)) {
      $alias_name = drush_sitealias_uri_to_site_dir($result_record['uri']);
    }
  }

  // Alias names contain an '@' when referenced, but do
  // not contain an '@' when defined.
  if (substr($alias_name,0,1) == '@') {
    $alias_name = substr($alias_name,1);
  }

  $exported_alias = var_export($result_record, TRUE);
  drush_print('$aliases[\'' . $alias_name . '\'] = ' . $exported_alias . ';');
}

/**
 * Use heuristics to attempt to convert from a site directory to a URI.
 * This function should only be used when the URI really is unknown, as
 * the mapping is not perfect.
 *
 * @param site_dir
 *   A directory, such as domain.com.8080.drupal
 *
 * @return string
 *   A uri, such as http://domain.com:8080/drupal
 */
function _drush_sitealias_site_dir_to_uri($site_dir) {
  // Protect IP addresses NN.NN.NN.NN by converting them
  // temporarily to NN_NN_NN_NN for now.
  $uri = preg_replace("/([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)/", "$1_$2_$3_$4", $site_dir);
  // Convert .[0-9]+. into :[0-9]+/
  $uri = preg_replace("/\.([0-9]+)\./", ":$1/", $uri);
  // Convert .[0-9]$ into :[0-9]
  $uri = preg_replace("/\.([0-9]+)$/", ":$1", $uri);
  // Convert .(com|net|org|info). into .(com|net|org|info)/
  $uri = str_replace(array('.com.', '.net.', '.org.', '.info.'), array('.com/', '.net/', '.org/', '.info/'), $uri);

  // If there is a / then convert every . after the / to /
  // Then again, if we did this we would break if the path contained a "."
  // I hope that the path would never contain a "."...
  $pos = strpos($uri, '/');
  if ($pos !== false) {
    $uri = substr($uri, 0, $pos + 1) . str_replace('.', '/', substr($uri, $pos + 1));
  }

  // n.b. this heuristic works all the time if there is a port,
  // it also works all the time if there is a port and no path,
  // but it does not work for domains such as .co.jp with no path,
  // and it can fail horribly if someone makes a domain like "info.org".
  // Still, I think this is the best we can do short of consulting DNS.

  // Convert from NN_NN_NN_NN back to NN.NN.NN.NN
  $uri = preg_replace("/([0-9]+)_([0-9]+)_([0-9]+)_([0-9]+)/", "$1.$2.$3.$4", $site_dir);

  return 'http://' . $uri;
}

/**
 * Validation callback for drush site-set.
 */
function drush_sitealias_site_set_validate() {
  if (!function_exists('posix_getppid')) {
    $args = array('!command' => 'site-set', '!dependencies' => 'POSIX');
    return drush_set_error('DRUSH_COMMAND_PHP_DEPENDENCY_ERROR', dt('Command !command needs the following PHP extensions installed/enabled to run: !dependencies.', $args));
  }
}

/**
 * Set the DRUPAL_SITE variable by writing it out to a temporary file that we
 * then source for persistent site switching.
 *
 * @param site
 *  A valid site specification.
 */
function drush_sitealias_site_set($site = '@none') {
  if ($filename = drush_sitealias_get_envar_filename()) {
    $last_site_filename = drush_sitealias_get_envar_filename('drush-drupal-prev-site-');
    if ($site == '-') {
      if (file_exists($last_site_filename)) {
        $site = file_get_contents($last_site_filename);
      }
      else {
        $site = '@none';
      }
    }
    if ($site == '@self') {
      $path = drush_cwd();
      $site_record = drush_sitealias_lookup_alias_by_path($path, TRUE);
      if (isset($site_record['#name'])) {
        $site = '@' . $site_record['#name'];
      }
      else {
        $site = '@none';
      }
      // Using 'site-set @self' is quiet if there is no change.
      $current = is_file($filename) ? trim(file_get_contents($filename)) : "@none";
      if ($current == $site) {
        return;
      }
    }
    if (_drush_sitealias_set_context_by_name($site)) {
      if (file_exists($filename)) {
        @unlink($last_site_filename);
        @rename($filename, $last_site_filename);
      }
      $success_message = dt("Site set to !site", array('!site' => $site));
      if ($site == '@none') {
        if (drush_delete_dir($filename)) {
          drush_print($success_message);
        }
      }
      elseif (drush_mkdir(dirname($filename), TRUE)) {
        if (file_put_contents($filename, $site)) {
          drush_print($success_message);
          drush_log(dt("Site information stored in !file", array('!file' => $filename)));
        }
      }
    }
    else {
      return drush_set_error('DRUPAL_SITE_NOT_FOUND', dt("Could not find a site definition for !site.", array('!site' => $site)));
    }
  }
}
