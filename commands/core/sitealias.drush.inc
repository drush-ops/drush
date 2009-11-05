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
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
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
  $site_list = array();
  $all_site_aliases = drush_get_option('site-aliases', array());
  foreach ($all_site_aliases as $alias => $alias_record) {
    $site_list[$alias] = drush_sitealias_get_site_specification($alias);
  }
  return $site_list;
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
      $path = explode('/', $filename);
      array_pop($path);
      $site = array_pop($path);
      $site_list[$site] = $root . '#' . $site;
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
function _drush_sitealias_user_specified_list() {
  $command = drush_get_command();
  $specifications = $command['arguments'];
  
  // First we'll get a list of all aliases and local sites
  $site_list = _drush_sitealias_all_list();
  
  // If the user provided no args, then we will return everything. 
  if (!empty($specifications)) {
    // Next we'll remove any item from the list that
    // is not in the list the user specified
    foreach ($site_list as $site => $sitespec) {
      if (!in_array($site, $specifications) && !in_array($sitespec, $specifications)) {
        unset($site_list[$site]);
      }
    }
    
    // Finally, we'll look for special values that
    // the user may have specified on the command line
    // and put those in as well.
    foreach ($specifications as $site) {
      if ($site == "local") {
        $site_list = array_merge($site_list, _drush_sitealias_site_list());
      }
      elseif ($site == "default") {
        $root = drush_get_context('DRUSH_DRUPAL_ROOT');
        $uri = drush_get_option(array('l', 'uri'));
        $site_list[$uri] = $root . '#' . $uri;
      }
    }
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
  foreach (_drush_sitealias_user_specified_list() as $site => $sitespec) {
    if (isset($full_output)) {
      _drush_sitealias_print_record($site);
    }
    elseif (isset($long_output)) {
      drush_print($sitespec);
    }
    else {
      drush_print($site);
    }
  }
}

/**
 * Compose a site specification for a named site alias.
 *
 * @param site_alias
 *   The name of the site alias to create a site specification for
 */
function drush_sitealias_get_site_specification($site_alias) {
  $all_site_aliases = drush_get_option('site-aliases', array());
  $result = '#' . $site_alias;
  if (array_key_exists($site_alias, $all_site_aliases)) {
    $site_alias_settings = $all_site_aliases[$site_alias];
    if (array_key_exists('uri', $site_alias_settings)) {
      $result = '#' . $site_alias_settings['uri'];
    }
    if (array_key_exists('path-aliases', $site_alias_settings)) {
      $path_aliases = $site_alias_settings['path-aliases'];
      if (array_key_exists('!root', $path_aliases)) {
        $result = $path_aliases['!root'] . $result;
      }
    }
    if (array_key_exists('remote-host', $site_alias_settings)) {
      $result = $site_alias_settings['remote-host'] . $result;
    }
  }
  return $result;
}

/**
 * Given a site alias name, print out a php-syntax
 * representation of it.
 *
 * @param site_alias
 *   The name of the site alias to print
 */
function _drush_sitealias_print_record($site_alias) {
  $output_db = drush_get_option('with-db');
  $output_db_url = drush_get_option('with-db-url');
  $output_optional_items = drush_get_option('with-optional');
  
  // Get the alias record for the specified site alias.
  // If the user wants database information included in
  // the record, then try to fetch that info from the
  // remote site.
  $alias_record = drush_sitealias_get_record($site_alias, isset($output_db) | isset($output_db_url));
  
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
  }
  $exported_alias = var_export($alias_record, TRUE);
  drush_print('$options[\'site-aliases\'][\'' . $alias_name . '\'] = ' . $exported_alias . ',');
}

