<?php
// $Id$

/**
 * Check to see if the first command-line arg or the
 * -l option is a site alias; if it is, copy its record
 * values to the 'alias' context.
 *
 * @return boolean
 *   TRUE if a site alias was found and processed.
 */
function drush_sitealias_check_arg() {
  $args = drush_get_arguments();

  // Test to see if the first arg is a site specification
  if (_drush_sitealias_set_context_by_name($args[0])) {
    array_shift($args);
    // We only need to expand the site specification
    // once, then we are done.
    drush_set_arguments($args);
    return TRUE;
  }

  // Return false to indicate that no site alias was specified.
  return FALSE;
}

/**
 * Get a site alias record given an alias name or site specification.
 *
 * If it is the name of a site alias, return the alias record from
 * the site aliases array.
 *
 * If it is the name of a folder in the 'sites' folder, construct
 * an alias record from values stored in settings.php.
 *
 * If it is a site specification, construct an alias record from the
 * values in the specification.
 *
 * Site specifications come in several forms:
 *
 * 1.) /path/to/drupal#sitename
 * 2.) user@server/path/to/drupal#sitename
 * 3.) user@server/path/to/drupal            (sitename == server)
 * 4.) user@server#sitename                  (only if $option['r'] set in some drushrc file on server)
 * 5.) #sitename                             (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 * 6.) sitename                              (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 *
 * Note that in the case of the first four forms, it is also possible
 * to add additional site variable to the specification using uri query
 * syntax.  For example:
 *
 *      user@server/path/to/drupal?db-url=...#sitename
 *
 * @param alias
 *   An alias name or site specification
 * @param db_settings_needed
 *   'true' if the caller needs the 'databases' record.  We
 *   Will look it up the first time it is requested.
 * @return array
 *   An alias record.
 */
function drush_sitealias_get_record($alias, $db_settings_needed = false) {
  // Check to see if the alias contains commas.  If it does, then
  // we will go ahead and make a site list record
  $alias_record = array();
  if (strpos($alias, ',') !== false) {
    // TODO:  If the site list contains any site lists, or site
    // search paths, then we should expand those and merge them
    // into this list longhand.
    $alias_record['site-list'] = explode(',', $alias);
    if ($db_settings_needed) {
      $alias_record['db-settings-needed'] = TRUE;
    }
  }
  else {
    $alias_record = _drush_sitealias_get_record($alias, $db_settings_needed);
  }
  if (!empty($alias_record) && !array_key_exists('name', $alias_record)) {
    $alias_record['name'] = $alias;
  }
  return $alias_record;
}

function _drush_sitealias_get_record($alias, $db_settings_needed = false) {
  // Sometimes getting an alias record involves loading settings.php or calling
  // backend invoke.  We'll cache all alias records fetched by this routine to
  // insure that we never have to do anything like that twice.
  $alias_record = drush_get_option('sitealias-' . $alias, array(), 'sitealias-cache');
  if (empty($alias_record)) {
    // Check to see if the provided parameter is in fact a defined alias.
    $all_site_aliases = drush_get_option('site-aliases', array());
    if (array_key_exists($alias, $all_site_aliases)) {
      $alias_record = $all_site_aliases[$alias];
    }
    // If the parameter is not an alias, then it is some form of
    // site specification (or it is nothing at all)
    elseif (isset($alias)) {
      // Cases 1.) - 4.):
      // We will check for a site specification if the alias has at least
      // two characters from the set '@', '/', '#'.
      if ((strpos($alias, '@') === FALSE ? 0 : 1) + (strpos($alias, '/') === FALSE ? 0 : 1) + (strpos($alias, '#') === FALSE ? 0 : 1) >= 2) {
        if ((substr($alias,0,7) != 'http://') && (substr($alias,0,1) != '/')) {
          // Add on a scheme so that "user:pass@server" will always parse correctly
          $parsed = parse_url('http://' . $alias);
        }
        else {
          $parsed = parse_url($alias);
        }
        // Special checking:  /path/to/root#all means all of the
        // sites at the given drupal root.  Convert to a 'site-search-path'
        // record, which will be converted to a 'site-list' later.
        if (array_key_exists('fragment', $parsed) && /*!array_key_exists('host', $parsed) &&*/ ($parsed['fragment'] == 'all')) {
          $alias_record['site-search-path'] = $parsed['path'];
        }
        else {
          // Copy various parts of the parsed URL into the appropriate records of the alias record
          foreach (array('user' => 'remote-user', 'pass' => 'remote-pass', 'host' => 'remote-host', 'fragment' => 'uri', 'path' => '!root') as $url_key => $option_key) {
            if (array_key_exists($url_key, $parsed)) {
              _drush_sitealias_set_record_element($alias_record, $option_key, $parsed[$url_key]);
            }
          }
          // If the site specification has a query, also set the query items
          // in the alias record.  This allows passing db_url as part of the
          // site specification, for example.
          if (array_key_exists('query', $parsed)) {
            foreach (explode('&', $parsed['query']) as $query_arg) {
              $query_components = explode('=', $query_arg);
              _drush_sitealias_set_record_element($alias_record, urldecode($query_components[0]), urldecode($query_components[1]));
            }
          }
          
          // Case 3.): If the URL contains a 'host' portion but no fragment, then set the uri to the host
          // Note: We presume that 'server' is the best default for case 3; without this code, the default would
          // be whatever is set in $options['l'] on the target machine's drushrc.php settings file.
          if (array_key_exists('host', $parsed) && !array_key_exists('fragment', $parsed)) {
            $alias_record['uri'] = $parsed['host'];
          }
        }
      }
      else {
        // Case 5.) and 6.):
        // If the alias is the name of a folder in the 'sites' directory,
        // then use it as a local site specification.
        $alias_record = _drush_sitealias_find_record_for_local_site($alias, $db_settings_needed);
      }

    }

    // Process site list related fields
    _drush_sitealias_check_sitelist_fields($alias_record);

    // If the alias record does not have a defined 'databases' entry,
    // then we'll need to look one up
    if ($db_settings_needed && !isset($alias_record['db-url']) && !isset($alias_record['databases']) && !isset($alias_record['site-list'])) {
      // If the alias record is remote, then we'll use backend_invoke to fetch the
      // database settings from the remote machine
      if (array_key_exists('remote-host', $alias_record)) {
        $data = array('root' => $alias_record['root'], 'uri' => $alias_record['uri'], 'all' => TRUE);
        $args = array();
        $values = drush_backend_invoke_args("sql-conf", $args, $data, 'GET', FALSE, $alias_record['path-aliases']['!drush-script'], $alias_record['remote-host'], $alias_record['remote-user']);
        $alias_record['databases'] = $values['object'];
      }
      // If the alias record is for a local machine, then look up and
      // use the database settings from the appropriate settings.php file.
      else {
        $alias_record = array_merge(_drush_sitealias_build_record_from_settings($alias_record['uri'], $alias_record['root']), $alias_record);
      }
    }

    // Add the static defaults
    _drush_sitealias_add_static_defaults($alias_record);

    // Fail fast if database settings are not available and the caller
    // said that they are required (but give site lists a pass)
    if ($db_settings_needed && !isset($alias_record['databases']) && !isset($alias_record['site-list'])) {
      drush_print("Error: could not get database spec when it was required for " . $alias);
      exit(1);
    }
    
    // Cache the result
    drush_set_option('sitealias-' . $alias, $alias_record, 'sitealias-cache');
  }
  return $alias_record;
}

function drush_sitealias_resolve_sitelist($alias_record) {
  $result_list = array();
  if (isset($alias_record)) {
    if (array_key_exists('site-list', $alias_record)) {
      $db_settings_needed = array_key_exists('db-settings-needed', $alias_record);
      foreach ($alias_record['site-list'] as $sitespec) {
        $one_result = drush_sitealias_get_record($sitespec, $db_settings_needed);
        if ($db_settings_needed) {
          $one_result['db-settings-needed'] = TRUE;
        }
        $result_list = array_merge($result_list, drush_sitealias_resolve_sitelist($one_result));
      }
    }
    else {
      // Q: drush_sitealias_uri_to_site_dir?
      // $result_list[$alias_record['name']] = $alias_record;
      $result_list[] = $alias_record;
    }
  }
  
  return $result_list;
}

function drush_sitealias_check_lists_alignment($source, $target) {
  // Check to see if the uri is the same in the source and target
  // lists for all items in the array.  This is a strong requirement
  // in D6; in D7, it is still highly convenient for the uri to
  // be the same, because the site folder name == the uri, and if
  // the uris match, then it is easier to rsync between remote machines.
  $is_aligned = TRUE;
  
  $i = 0;
  foreach ($source as $one_source) {
    if ((!isset($target[$i])) || (!_drush_sitelist_check_site_records($one_source, $target[$i]))) {
      $is_aligned = FALSE;
      break;
    }
    ++$i;
  }
  
  return $is_aligned;
}

function drush_sitelist_align_lists(&$source, &$target, &$source_result, &$target_result) {
  $source_result = array();
  $target_result = array();
  
  foreach ($source as $key => $one_source) {
    $one_target = _drush_sitelist_find_in_list($one_source, $target);
    if ($one_target !== FALSE) {
      $source_result[] = $one_source;
      $target_result[] = $one_target;
      unset($source[$key]);
    }
  }
  
  $source = $source_result;
  $target = $target_result;
}

function _drush_sitelist_find_in_list($one_source, &$target) {
  $result = FALSE;
  
  foreach ($target as $key => $one_target) {
    if(_drush_sitelist_check_site_records($one_source, $one_target)) {
      $result = $one_target;
      unset($target[$key]);
    }
  }
  
  return $result;
}

function _drush_sitelist_check_site_records($source, $target) {
  if ((array_key_exists('uri', $source)) && (array_key_exists('uri', $target)) && ($source['uri'] == $target['uri'])) {
    return TRUE;
  }
  return FALSE;
}

function drush_sitealias_resolve_sitespecs($site_specifications, $db_settings_needed = false) {
  $result_list = array();
  if (!empty($site_specifications)) {
    foreach ($site_specifications as $site) {
      $alias_record = drush_sitealias_get_record($site, $db_settings_needed);
      $result_list = array_merge($result_list, drush_sitealias_resolve_sitelist($alias_record));
    }
  }
  return $result_list;
}

function _drush_sitealias_check_sitelist_fields(&$alias_record) {
  // If there is a 'from-list' entry, then build a derived
  // list based on the site list with the given name.
  if (array_key_exists('from-list', $alias_record)) {
    // danger of infinite loops... move to transient defaults?
    $from_record = drush_sitealias_get_record($alias_record['from-list']);
    $from_list = drush_sitealias_resolve_sitelist($from_record);
    $derived_list = array();
    foreach ($from_list as $one_record) {
      $derived_record = _drush_sitealias_derive_record($one_record, $alias_record);
      $derived_list[] = drush_sitealias_alias_record_to_spec($derived_record);
    }
    
    $alias_record = array();
    if (!empty($derived_list)) {
      $alias_record['site-list'] = $derived_list;
    }
  }
  // If there is a 'site-search-path' entry, then build
  // a 'site-list' entry from all of the sites that can be
  // found in the search path.
  if (array_key_exists('site-search-path', $alias_record)) {
    // TODO:  Is there any point in merging the sites from
    // the search path with any sites already listed in the
    // 'site-list' entry?  For now we'll just overwrite.
    $search_path = $alias_record['site-search-path'];
    if (!is_array($search_path)) {
      $search_path = explode(',', $search_path);
    }
    $found_sites = _drush_sitealias_find_local_sites($search_path);
    $alias_record['site-list'] = $found_sites;
    // The 'unordered-list' flag indicates that the order of the items in the site list is not stable.
    $alias_record['unordered-list'] = '1';
    // DEBUG: var_export($alias_record, FALSE);
  }
  if (array_key_exists('site-list', $alias_record)) {
    if (!is_array($alias_record['site-list'])) {
      $alias_record['site_list'] = explode(',', $alias_record['site-list']);
    }
  }
}

/**
 * Add "static" default values to the given alias record.  The
 * difference between a static default and a transient default is
 * that static defaults -always- exist in the alias record, and
 * they are cached, whereas transient defaults are only added
 * if the given drush command explicitly adds them.
 *
 * @param alias_record
 *   An alias record with most values already filled in
 */
function _drush_sitealias_add_static_defaults(&$alias_record) {
  // If there is a 'db-url' entry but not 'databases' entry, then we will
  // build 'databases' from 'db-url' so that drush commands that use aliases
  // can always count on using a uniform 'databases' array.
  if (isset($alias_record['db-url']) && !isset($alias_record['databases'])) {
    $alias_record['databases'] = drush_sitealias_convert_db_from_db_url($alias_record['db-url']);
  }
  // If there is a 'root' entry, then copy it to the '!root'
  // path alias
  if (array_key_exists('root', $alias_record) && (!array_key_exists('path-aliases', $alias_record) || !array_key_exists('!root', $alias_record['path-aliases']))) {
    $alias_record['path-aliases']['!root'] = $alias_record['root'];
  }
  // If there is a '!root' entry in the path aliases but no
  // 'root' entry, then copy the path alias to 'root'
  if (!array_key_exists('root', $alias_record) && array_key_exists('path-aliases', $alias_record) && array_key_exists('!root', $alias_record['path-aliases'])) {
    $alias_record['root'] = $alias_record['path-aliases']['!root'];
  }
}

function _drush_sitealias_derive_record($from_record, $modifying_record) {
  $result = $from_record;
  
  // If there is a 'remote-user' in the modifying record, copy it.
  if (array_key_exists('remote-user', $modifying_record)) {
    $result['remote-user'] = $from_record['remote_user'];
  }
  // If there is a 'remote-host', then:
  //   If it is empty, clear the remote host in the result record
  //   If it ends in '.', then prepend it to the remote host in the result record
  //   Otherwise, copy it to the result record
  if (array_key_exists('remote-host', $modifying_record)) {
    $remote_host_modifier = $modifying_record['remote-host'];
    if(empty($remote_host_modifier)) {
      unset($result['remote-host']);
      unset($result['remote-user']);
    }
    elseif ($remote_host_modifier[strlen($remote_host_modifier)-1] == '.') {
      $result['remote-host'] = $remote_host_modifier . $result['remote-host'];
    }
    else {
      $result['remote-host'] = $remote_host_modifier;
    }
  }
  // If there is a 'root', then:
  //   If it begins with '/', copy it to the result record
  //   Otherwise, append it to the result record 
  if (array_key_exists('root', $modifying_record)) {
    $root_modifier = $modifying_record['root'];
    if($root_modifier[0] == '/') {
      $result['root'] = $root_modifier;
    }
    else {
      $result['root'] = $result['root'] . '/' . $root_modifier;
    }
  }
  // Poor man's realpath: take out the /../ with preg_replace.
  // (realpath fails if the files in the path do not exist)
  while(strpos($result['root'], '/../') !== FALSE) {
    $result['root'] = preg_replace('/\w+\/\.\.\//', '', $result['root']);
  }
  
  // TODO:  Should we allow the uri to be transformed?
  // I think that if the uri does not match, then you should
  // always build the list by hand, and not rely on '_drush_sitealias_derive_record'.
  
  return $result;
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
    
    // TODO:  we should handle 'site-list' records too.
    if (array_key_exists('site-list', $alias_record)) {
      // TODO:  we should actually expand the site list and recompose it
      $result = implode(',', $alias_record['site-list']);
    }
    else {
      // There should always be a uri
      if (array_key_exists('uri', $alias_record)) {
        $result = '#' . str_replace('http://', '', $alias_record['uri']);
      }
      // There should always be a root
      if (array_key_exists('root', $alias_record)) {
        $result = $alias_record['root'] . $result;
      }
      if (array_key_exists('remote-host', $alias_record)) {
        $result = $alias_record['remote-host'] . $result;
        if (array_key_exists('remote-user', $alias_record)) {
          $result = $alias_record['remote-user'] . '@' . $result;
        }
      }

      // add the database info to the specification if desired
      if ($with_db) {
        $result = $result . '?db-url=' . urlencode($alias_record['db-url']);
      }
    }
    
    return $result;
}

/**
 * Search for drupal installations in the search path.
 *
 * @param search_path
 *   An array of drupal root folders
 *
 * @return
 *   An array of site specifications (/path/to/root#sitename.com)
 */
function _drush_sitealias_find_local_sites($search_path) {
  $result = array();
  foreach ($search_path as $a_drupal_root) {
    $result = array_merge($result, _drush_find_local_sites_at_root($a_drupal_root));
  }
  return $result;  
}

/**
 * Return a list of all of the local sites at the specified drupal root.
 */
function _drush_find_local_sites_at_root($a_drupal_root = '') {
  $site_list = array();
  $base_path = (empty($a_drupal_root) ? drush_get_context('DRUSH_DRUPAL_ROOT') : $a_drupal_root );
  if (drush_valid_drupal_root($base_path)) {
    $base_path .= '/sites';
  }
  
  // TODO:  build a cache keyed off of $base_path (realpath($base_path)?),
  // so that it is guarenteed that the lists returned will definitely be
  // exactly the same should this routine be called twice with the same path.
  
  $files = drush_scan_directory($base_path, '/settings\.php/', array('.', '..', 'CVS', 'all'));
  foreach ($files as $filename => $info) {
    if ($info->basename == 'settings.php') {
      // First we'll resolve the realpath of the settings.php file,
      // so that we get the correct drupal root when symlinks are in use.
      $real_sitedir = dirname(realpath($filename));
      $real_root = drush_locate_root($filename);
      if ($real_root !== FALSE) {
        $a_drupal_site = $real_root . '#' . basename($real_sitedir);
      }
      // If the symlink points to some folder outside of any drupal
      // root, then we'll use the
      else {
        $uri = drush_sitealias_site_dir_from_filename($filename);
        $a_drupal_site = $a_drupal_root . '#' . $uri;
      }
      // Add the site if it isn't already in the array
      if (!in_array($a_drupal_site, $site_list)) {
        $site_list[] = $a_drupal_site;
      }
    }
  }
  return $site_list;
}

/**
 * Add "transient" default values to the given alias record.  The
 * difference between a static default and a transient default is
 * that static defaults -always- exist in the alias record,
 * whereas transient defaults are only added if the given drush
 * command explicitly calls this function.  The other advantage
 * of transient defaults is that it is possible to differentiate
 * between a default value and an unspecified value, since the
 * transient defaults are not added until requested.
 *
 * Since transient defaults are not cached, you should avoid doing
 * expensive operations here.  To be safe, drush commands should
 * avoid calling this function more than once.
 *
 * @param alias_record
 *   An alias record with most values already filled in
 */
function _drush_sitealias_add_transient_defaults(&$alias_record) {
  if (isset($alias_record['path-aliases'])) {
    // Add the path to the drush folder to the path aliases as !drush
    if (!array_key_exists('!drush', $alias_record['path-aliases'])) {
      if (array_key_exists('!drush-script', $alias_record['path-aliases'])) {
        $alias_record['path-aliases']['!drush'] = dirname($alias_record['path-aliases']['!drush-script']);
      }
      else {
        $alias_record['path-aliases']['!drush'] = dirname($GLOBALS['argv'][0]);
      }
    }
    // Add the path to the site folder to the path aliases as !site
    if (!array_key_exists('!site', $alias_record['path-aliases']) && array_key_exists('uri', $alias_record)) {
      $alias_record['path-aliases']['!site'] = 'sites/' . $alias_record['uri'] . '/';
    }
  }
}

/**
 * Find a record in the site aliases list for a local site with
 * the requested uri, if one exists.  Otherwise, build one from
 * the settings.php file for the specified site.
 */
function _drush_sitealias_find_record_for_local_site($alias, $db_settings_needed = false) {
  $alias_record = array();
  $use_name_from_alias = false;

  // The alias "current" is a synonym for whatever site
  // has been specified via -l or -uri
  if ($alias == "current") {
    $alias = drush_get_option(array('l', 'uri'));
    $use_name_from_alias = true;
  }

  // Clip off the leading '#' if it is there
  if (substr($alias,0,1) == '#') {
    // TODO: if there is a leading #, then search for
    // matching sites using the site search path
    $alias = substr($alias,1);
  }

  // This function may be called during the drush bootstrap
  // (i.e., from drush_sitealias_check_arg()), when 'DRUSH_DRUPAL_ROOT'
  // has not been set.  It may also be called after bootstraping
  // has finished (e.g. to process an argument from rsync or sql-sync)
  // when it would be wasteful to call drush_locate_root again.
  // If we find an alias or site specification during drush_sitealias_check_arg(),
  // then we will set the 'root' option.  If we do not, though,
  // we will end up calling drush_locate_root every time this
  // function is called.
  // TODO: Would it be valid to use some other mechanism to test
  // to see if the drupal root has already been cached?  Maybe
  // drush should just drush_set_option('root', $drupal_root) rather
  // than setting a context and a bootstrap value?
  //$drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  //$drupal_root = drush_bootstrap_value('drupal_root');
  $drupal_root = drush_get_option(array('r', 'root'), drush_locate_root());

  $all_site_aliases = drush_get_option('site-aliases', array());
  foreach ($all_site_aliases as $one_alias_name => $one_alias_record) {
    if (!isset($one_alias_record['remote-host']) && isset($one_alias_record['path-aliases']) && isset($one_alias_record['path-aliases']['!root']) && ($one_alias_record['uri'] == $alias) && ($one_alias_record['path-aliases']['!root'] == $drupal_root)) {
      $alias_record = $one_alias_record;
    }
  }

  if (empty($alias_record) || ($db_settings_needed && !isset($alias_record['db-url']) && !isset($alias_record['databases'])              )) {
    $alias_record = array_merge(_drush_sitealias_build_record_from_settings($alias, $drupal_root), $alias_record);
  }

  // If the alias was looked up via the name "current", then
  // plug in the actual alias name that was used to find it.
  if (!empty($alias_record) && $use_name_from_alias) {
    $alias_record['name'] = $drupal_root . '#' . $alias;
  }

  return $alias_record;
}

/**
 * Use the information from a particular settings.php file
 * to build an alias record.
 *
 * @param alias
 *   The name of the site in the 'sites' folder to convert
 * @return array
 *   An alias record.
 */
function _drush_sitealias_build_record_from_settings($alias, $drupal_root = null) {
  $alias_record = array();

  // Clip off the leading '#' if it is there
  if (substr($alias,0,1) == '#') {
    $alias = substr($alias,1);
  }

  if (!isset($drupal_root)) {
    //$drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
    $drupal_root = drush_get_option(array('r', 'root'), drush_locate_root());
  }

  if (isset($drupal_root)) {
    $alias_dir = drush_sitealias_uri_to_site_dir($alias);
    $site_settings_file = $drupal_root . '/sites/' . $alias_dir . '/settings.php';
    $alias_record = drush_sitealias_build_record_from_settings_file($site_settings_file, $alias, $drupal_root);
  }

  return $alias_record;
}

function drush_sitealias_build_record_from_settings_file($site_settings_file, $alias = null, $drupal_root = null) {
  $alias_record = array();

  if (!isset($drupal_root)) {
    $drupal_root = drush_get_option(array('r', 'root'), drush_locate_root());
  }

  if (file_exists($site_settings_file)) {
    global $databases, $db_url, $base_url;
    $base_url = null;
    require $site_settings_file;
    if (isset($db_url)) {
      $alias_record['db-url'] = $db_url;
    }
    else {
      $alias_record['databases'] = $databases;
    }
    $alias_record['path-aliases'] = array(
      '!root' => $drupal_root,
      /*'!files' => '/path/to/file/storage', */
      /*'!dump' => '/path/to/sql/dumpfile', */ );

    if (isset($alias)) {
      $alias_record['uri'] = $alias;
    }
    elseif ($base_url != null) {
      $alias_record['uri'] = $base_url;
    }
    else {
      $alias_record['uri'] = _drush_sitealias_site_dir_to_uri(drush_sitealias_site_dir_from_filename($site_settings_file));
    }
  }

  return $alias_record;
}

/**
 * Pull the site directory from the path to settings.php
 *
 * @param site_settings_file
 *   path to settings.php
 *
 * @return string
 *   the site directory component of the path to settings.php
 */
function drush_sitealias_site_dir_from_filename($site_settings_file) {
  return basename(dirname($site_settings_file));
}

/**
 * Convert from a URI to a site directory.
 *
 * @param uri
 *   A uri, such as http://domain.com:8080/drupal
 * @return string
 *   A directory, such as domain.com.8080.drupal
 */
function drush_sitealias_uri_to_site_dir($uri) {
  return str_replace(array('http://', '/', ':'), array('', '.', '.'), $uri);
}

/**
 * Convert from an old-style database URL to an array of database settings
 *
 * @param db_url
 *   A Drupal 6 db-url string to convert.
 * @return array
 *   An array of database values.
 */
function drush_convert_db_from_db_url($db_url) {
  if (is_array($db_url)) {
    $url = parse_url($db_url['default']);
  }
  else {
    $url = parse_url($db_url);
  }
  // Fill in defaults to prevent notices.
  $url += array(
    'driver' => NULL,
    'user' => NULL,
    'pass' => NULL,
    'port' => NULL,
    'database' => NULL,
  );
  $url = (object)$url;
  return array(
    'driver' => $url->scheme == 'mysqli' ? 'mysql' : $url->scheme,
    'username' => urldecode($url->user),
    'password' => urldecode($url->pass),
    'port' => urldecode($url->port),
    'host' => urldecode($url->host),
    'database' => substr(urldecode($url->path), 1), // skip leading '/' character
  );
}

function drush_sitealias_convert_db_from_db_url($db_url) {
  $result = array();

  if (is_array($db_url)) {
    $default_db = array();

    foreach ($db_url as $db_name => $db_urlstr) {
      $default_db[$db_name] = drush_convert_db_from_db_url($db_urlstr);
    }

    $result['default'] = $default_db;
  }
  else {
    $result = array('default' => array('default' => drush_convert_db_from_db_url($db_url)));
  }

  return $result;
}

/**
 * Utility function used by drush_get_alias; keys that start with
 * '!' are path aliases, the rest are entries in the alias record.
 */
function _drush_sitealias_set_record_element(&$alias_record, $key, $value) {
  if (substr($key,0,1) == '!') {
    $alias_record['path-aliases'][$key] = $value;
  }
  elseif (!empty($key)) {
    $alias_record[$key] = $value;
  }
}

/**
 * Looks up the specified alias record and calls through to
 * drush_sitealias_set_alias_context, below.
 *
 * @param alias
 *   The name of the alias record
 * @param prefix
 *   The prefix value to afix to the beginning of every
 *   key set.
 * @return boolean
 *   TRUE is an alias was found and processed.
 */
function _drush_sitealias_set_context_by_name($alias, $prefix = '') {
  $site_alias_settings = drush_sitealias_get_record($alias);
  if (!empty($site_alias_settings)) {
    drush_sitealias_set_alias_context($site_alias_settings, $prefix);
    return TRUE;
  }
  return FALSE;
}

/**
 * Given a site alias record, copy selected fields from it
 * into the drush 'alias' context.  The 'alias' context has
 * lower precedence than the 'options' context, so values
 * set by an alias record can be overridden by command-line
 * parameters.
 *
 * @param site_alias_settings
 *   An alias record
 * @param prefix
 *   The prefix value to afix to the beginning of every
 *   key set.  For example, if this function is called once with
 *   'source-' and again with 'destination-' prefixes, then the
 *   source database records will be stored in 'source-databases',
 *   and the destination database records will be in
 *   'destination-databases'.
 */
function drush_sitealias_set_alias_context($site_alias_settings, $prefix) {
  // backend invoke needs 'root' and 'uri' on the command line, so we will
  // handle these two specially
  $special = empty($prefix) ? array('root', 'uri') : array();
  // Transfer all non-array options from the site alias to the drush options
  // in the 'alias' context.
  foreach ($site_alias_settings as $key => $value) {
    if (!is_array($value) || ($key == "databases") || ($key == "site-list")) {
      drush_set_option($prefix . $key, $value, in_array($key, $special) ? 'options' : 'alias');
    }
  }
  // Transfer selected path aliases to the drush options.
  if (array_key_exists('path-aliases', $site_alias_settings)) {
    foreach (array('!root', '!drush', '!drush-script', '!dump', '!include') as $key) {
      if (array_key_exists($key, $site_alias_settings['path-aliases'])) {
        drush_set_option($prefix . substr($key, 1), $site_alias_settings['path-aliases'][$key], 'alias');
      }
    }
  }
  // If there are prefix-specific options (e.g. 'source-options' or 'target-options'),
  // then transfer those values as well.
  drush_sitealias_apply_special_alias_record_options($site_alias_settings, $prefix);
}

/**
 * Looks up the specified alias record and then calls through
 * to drush_sitealias_apply_special_alias_record_options, below.
 *
 * @param alias
 *   The name of the alias record.
 * @param prefix
 *   The prefix value to afix to the beginning of every
 *   key set.
 */
function drush_sitealias_apply_special_alias_options($alias, $prefix) {
  if ($prefix != '') {
    $site_alias_settings = drush_sitealias_get_record($alias);
    if (!empty($site_alias_settings)) {
      drush_sitealias_apply_special_alias_record_options($site_alias_settings, $prefix);
    }
  }
}

/**
 * Site alias records can have special options sections,
 * one for every kind of prefix used with 'drush_sitealias_set_alias_context.
 * The options stored in this record are copied to the 'alias'
 * context whenever that prefix is used.
 *
 * @param site_alias_settings
 *   The alias record.
 * @param prefix
 *   The prefix value to afix to the beginning of every
 *   key set.
 */
function drush_sitealias_apply_special_alias_record_options($site_alias_settings, $prefix) {
  if ($prefix != '') {
    if ((array_key_exists($prefix . 'options', $site_alias_settings))) {
      foreach ($site_alias_settings[$prefix . 'options'] as $key => $value) {
        drush_set_option($key, $value, 'alias');
      }
    }
  }
}
