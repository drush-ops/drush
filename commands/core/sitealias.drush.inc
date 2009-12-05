<?php
// $Id$

/**
 * @file
 *   Site alias commands. @see example.drushrc.php for details.
 */

function sitealias_drush_help($section) {
  switch ($section) {
    case 'drush:site alias':
      return dt('Print an alias record.');
  }
}

function sitealias_drush_command() {
  $items = array();

  $items['site alias'] = array(
    'callback' => 'drush_sitealias_print',
    'description' => 'Print site alias records for all known site aliases and local sites.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'arguments' => array(
      'site' => 'Site specification alias to print',
    ),
    'options' => array(
      '--full' => 'Print the full alias record for each site.',
      '--long' => 'Print the long-form site specification for each site.',
      '--short' => 'Print only the site alias name (default).',
      '--with-db' => 'Include the databases structure in the full alias record.',
      '--with-db-url' => 'Include the short-form db-url in the full alias record.',
      '--no-db' => 'Do not include the database record in the full alias record (default).',
      '--with-optional' => 'Include optional default items.',
    ),
    'aliases' => array('sa'),
    'examples' => array(
      'drush site alias' => 'Print an alias record for the default site.',
      'drush site alias dev' => 'Print an alias record for the alias \'dev\'.',
    ),
  );
  return $items;
}

/**
 * Return a list of all site aliases known to drush.
 *
 * The array key is the site alias name, and the array value
 * is the site specification for the given alias.
 */
function _drush_sitealias_alias_list() {
  return drush_get_option('site-aliases', array());
}

/**
 * Return a list of all of the local sites at the current drupal root.
 *
 * The array key is the site folder name, and the array value
 * is the site specification for that site.
 */
function _drush_sitealias_site_list() {
  $site_list = array();
  $base_path = drush_get_context('DRUSH_DRUPAL_ROOT') . '/sites';
  $files = drush_scan_directory($base_path, '/settings\.php/', array('.', '..', 'CVS', 'all'));
  foreach ($files as $filename => $info) {
    if ($info->basename == 'settings.php') {
      $alias_record = drush_sitealias_build_record_from_settings_file($filename);
      $uri = drush_sitealias_site_dir_from_filename($filename);
      $site_list[$uri] = $alias_record;
    }
  }
  return $site_list;
}

/**
 * Return the list of all site aliases and all local sites.
 */
function _drush_sitealias_all_list() {
  return array_merge(_drush_sitealias_alias_list(), _drush_sitealias_site_list());
}

/**
 * Return the list of sites (aliases or local) that the
 * user specified on the command line.  If none were specified,
 * then all are returned.
 */
function _drush_sitealias_user_specified_list($db_settings_needed = false) {
  $command = drush_get_command();
  $specifications = $command['arguments'];
  $site_list = array();
  
  // Iterate over the arguments and convert them to alias records 
  if (!empty($specifications)) {
    foreach ($specifications as $site) {
      $alias_record = drush_sitealias_get_record($site, $db_settings_needed);
      if (!empty($alias_record)) {
        // If the alias record has a better name for itself than the
	// one that was used to search for it, then it will put it
	// into the 'name' element.
        if (array_key_exists('name', $alias_record)) {
          $site = $alias_record['name'];
	}
        $site_list[drush_sitealias_uri_to_site_dir($site)] = $alias_record;
      }
    }
  }
  // If the user provided no args, then we will return everything. 
  else {
    $site_list = _drush_sitealias_all_list();
  }
  
  return $site_list;
}

/**
 * Print out the specified site aliases using the format
 * specified.
 */
function drush_sitealias_print() {
  $full_output = drush_get_option('full');
  $long_output = drush_get_option('long');
  $with_db = (drush_get_option('with-db') != null) || (drush_get_option('with-db-url') != null);
  $site_list = _drush_sitealias_user_specified_list($with_db);
    
  foreach ($site_list as $site => $alias_record) {
    if (isset($full_output)) {
      _drush_sitealias_print_record($alias_record, $site);
    }
    elseif (isset($long_output)) {
      $site_spec = drush_sitealias_alias_record_to_spec($alias_record, $with_db);
      drush_print($site_spec);
    }
    else {
      drush_print($site);
    }
  }
}

/**
 * Convert from an alias record to a site specification
 *
 * @param alias_record
 *   The full alias record to convert
 *
 * @param with_db
 *   True if the site specification should include a ?db-url term
 *
 * @return string
 *   The site specification
 */
function drush_sitealias_alias_record_to_spec($alias_record, $with_db = false) {
    $result = '';
    if (array_key_exists('uri', $alias_record)) {
      $result = '#' . str_replace('http://', '', $alias_record['uri']);
    }
    if (array_key_exists('path-aliases', $alias_record)) {
      $path_aliases = $alias_record['path-aliases'];
      if (array_key_exists('!root', $path_aliases)) {
        $result = $path_aliases['!root'] . $result;
      }
    }
    if (array_key_exists('remote-host', $alias_record)) {
      $result = $alias_record['remote-host'] . $result;
    }
    
    // add the database info to the specification if desired
    if ($with_db) {
      $result = $result . '?db-url=' . urlencode($alias_record['db-url']);
    }
    
    return $result;
}

/**
 * Given a site alias name, print out a php-syntax
 * representation of it.
 *
 * @param alias_record
 *   The name of the site alias to print
 */
function _drush_sitealias_print_record($alias_record, $site_alias = '') {
  $output_db = drush_get_option('with-db');
  $output_db_url = drush_get_option('with-db-url');
  $output_optional_items = drush_get_option('with-optional');
    
  // Include the optional items, if requested
  if ($output_optional_items) {
    _drush_sitealias_add_transient_defaults($alias_record);
  }
  
  // If the user specified --with-db-url, then leave the
  // 'db-url' entry in the alias record (unless it is not
  // set, in which case we will leave the 'databases' record instead).
  if (isset($output_db_url)) {
    if (isset($alias_record['db-url'])) {
      unset($alias_record['databases']);
    }
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
  
  // The alias name will be the same as the site alias name,
  // unless the user specified some other name on the command line.
  $alias_name = drush_get_option('alias-name');
  if (!isset($alias_name)) {
    $alias_name = $site_alias;
    if (empty($alias_name)) {
      $alias_name = drush_sitealias_uri_to_site_dir($alias_record['uri']);
    }
  }
  
  // We don't want the name to go into the output
  unset($alias_record['name']);

  $exported_alias = var_export($alias_record, TRUE);
  drush_print('$options[\'site-aliases\'][\'' . $alias_name . '\'] = ' . $exported_alias . ';');
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

  // Convert .[0-9]+. into :[0-9]+/
  $uri = preg_replace("/\.([0-9]+)\./", ":$1/", $site_dir);
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
  
  return 'http://' . $uri;
}
