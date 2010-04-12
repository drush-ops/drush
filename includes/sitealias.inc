<?php
// $Id$

/**
 * @file
 * The site alias API.
 *
 * Run commands on remote server(s).
 * @see example.drushrc.php
 * @see http://drupal.org/node/670460
 */

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
 * Given an array of site specifications, resolve each one in turn and
 * return an array of alias records.  If you only want a single record,
 * it is preferable to simply call drush_sitealias_get_record directly.
 *
 * @param $site_specifications
 *   An array of site specificatins.  @see drush_sitealias_get_record
 * @return
 *   An array of alias records
 */
function drush_sitealias_resolve_sitespecs($site_specifications) {
  $result_list = array();
  if (!empty($site_specifications)) {
    foreach ($site_specifications as $site) {
      $alias_record = drush_sitealias_get_record($site);
      $result_list = array_merge($result_list, drush_sitealias_resolve_sitelist($alias_record));
    }
  }
  return $result_list;
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
 * @return array
 *   An alias record, or empty if none found.
 */
function drush_sitealias_get_record($alias) {
  // Check to see if the alias contains commas.  If it does, then
  // we will go ahead and make a site list record
  $alias_record = array();
  if (strpos($alias, ',') !== false) {
    // TODO:  If the site list contains any site lists, or site
    // search paths, then we should expand those and merge them
    // into this list longhand.
    $alias_record['site-list'] = explode(',', $alias);
  }
  else {
    $alias_record = _drush_sitealias_get_record($alias);
  }
  if (!empty($alias_record) && !array_key_exists('name', $alias_record)) {
    $alias_record['name'] = drush_sitealias_uri_to_site_dir($alias);
  }
  return $alias_record;
}

/*
 * This is a continuation of drush_sitealias_get_record, above.  It is
 * not intended to be called directly.
 */
function _drush_sitealias_get_record($alias, $alias_context = NULL) {
  // Before we do anything else, load $alias if it needs to be loaded
  _drush_sitealias_load_alias($alias, $alias_context);

  // Check to see if the provided parameter is in fact a defined alias.
  $all_site_aliases =& drush_get_context('site-aliases');
  if (array_key_exists($alias, $all_site_aliases)) {
    $alias_record = $all_site_aliases[$alias];
  }
  // If the parameter is not an alias, then it is some form of
  // site specification (or it is nothing at all)
  else {
    if (isset($alias)) {
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
        // Copy various parts of the parsed URL into the appropriate records of the alias record
        foreach (array('user' => 'remote-user', 'pass' => 'remote-pass', 'host' => 'remote-host', 'fragment' => 'uri', 'path' => 'root') as $url_key => $option_key) {
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
        
        // Special checking:  relative aliases embedded in a path
        $relative_alias_pos = strpos($alias_record['root'], '/@');
        if ($relative_alias_pos !== FALSE) {
          // Special checking: /path/@sites
          $base = substr($alias_record['root'], 0, $relative_alias_pos);
          $relative_alias = substr($alias_record['root'], $relative_alias_pos + 1);
          if (drush_valid_drupal_root($base) || ($relative_alias == '@sites')) {
            drush_sitealias_create_sites_alias($base);
            $alias_record = drush_sitealias_get_record($relative_alias);
          }
          else {
            $alias_record = array();
          }
        }
      }
      else {
        // Case 5.) and 6.):
        // If the alias is the name of a folder in the 'sites' directory,
        // then use it as a local site specification.
        $alias_record = _drush_sitealias_find_record_for_local_site($alias);
      }

    }
  }

  if (!empty($alias_record)) {
    // Load the drush config file if there is one associated with this alias
    if (!isset($alias_record['remote']) && !isset($alias_record['loaded-config'])) {
      $alias_site_dir = drush_sitealias_local_site_path($alias_record);

      if (isset($alias_site_dir)) {
        // Add the sites folder of this site to the alias search path list
        drush_sitealias_add_to_alias_path($alias_site_dir);

        if (!isset($alias_record['config'])) {
          $alias_record['config'] = realpath($alias_site_dir . '/drushrc.php');
        }
      }
      if (isset($alias_record['config']) && file_exists($alias_record['config'])) {
        drush_load_config_file('site', $alias_record['config']);
        $alias_record['loaded-config'] = TRUE;
      }
      unset($alias_record['config']);

      // Handle nested alias definitions and command-specific options.
      drush_set_config_special_contexts($alias_record);
    }

    // Add the static defaults
    _drush_sitealias_add_static_defaults($alias_record);

    // Cache the result with all of its calculated values
    $all_site_aliases[$alias] = $alias_record;
  }

  return $alias_record;
}

/**
 * Add a path to the array of paths where alias files are searched for.
 *
 * @param $add_path
 *   A path to add to the search path (or NULL to not add any).
 *   Once added, the new path will remain available until drush
 *   exits.
 * @return
 *   An array of paths containing all values added so far
 */
function drush_sitealias_add_to_alias_path($add_path) {
  static $site_paths = array();

  if ($add_path != NULL) {
    if (!is_array($add_path)) {
      $add_path = explode(':', $add_path);
    }
    $site_paths = array_unique(array_merge($site_paths, $add_path));
  }
  return $site_paths;
}

/**
 * Return the array of paths where alias files are searched for.
 *
 * @param $alias_path_context
 *   If the alias being looked up is part of a relative alias,
 *   the alias path context specifies the context of the primary
 *   alias the new alias is rooted from.  Alias files stored in
 *   the sites folder of this context, or inside the context itself
 *   takes priority over any other search path that might define
 *   a similarly-named alias.  In this way, multiple sites can define
 *   a '@peer' alias.
 * @return
 *   An array of paths
 */
function drush_sitealias_alias_path($alias_path_context = NULL) {
  if (isset($alias_path_context)) {
    return array(drush_sitealias_local_site_path($alias_path_context));
  }
  else {
    // We get the current list of site paths by adding NULL
    // (nothing) to the path list, which is a no-op
    $site_paths = drush_sitealias_add_to_alias_path(NULL);

    $alias_path = (array) drush_get_option('alias-path', array());
    if (empty($alias_path)) {
      $alias_path[] = drush_get_context('ETC_PREFIX', '') . '/etc/drush';
      $alias_path[] = dirname(__FILE__) . '/..';
      $alias_path[] = dirname(__FILE__) . '/../aliases';
      if(!is_null(drush_server_home())) {
        $alias_path[] = drush_server_home() . '/.drush';
      }
    }

    return array_merge($alias_path, $site_paths);
  }
}

/**
 * Return the full path to the site directory of the
 * given alias record.
 *
 * @param $alias_record
 *   The alias record
 * @return
 *   The path to the site directory of the associated
 *   alias record, or NULL if the record is not a local site.
 */
function drush_sitealias_local_site_path($alias_record) {
  $result = NULL;

  if (isset($alias_record['uri']) && isset($alias_record['root']) && !isset($alias_record['remote_host'])) {
    $result = realpath($alias_record['root'] . '/sites/' . drush_sitealias_uri_to_site_dir($alias_record['uri']));
  }

  return $result;
}

/**
 * Check and see if an alias definition for $alias is available.
 * If it is, load it into the list of aliases cached in the
 * 'site-aliases' context.
 *
 * @param $alias
 *   The name of the alias to load in ordinary form ('@name')
 * @param $alias_path_context
 *   When looking up a relative alias, the alias path context is
 *   the primary alias that we will start our search from.
 */
function _drush_sitealias_load_alias($alias, $alias_path_context = NULL) {
  $all_site_aliases = drush_get_context('site-aliases');
  $result = array();

  // Check to see if this is a relative alias ('@site/@peer')
  $relative_alias_pos = strpos($alias, '/@');
  if ($relative_alias_pos !== false) {
    $primary_alias = substr($alias,0,$relative_alias_pos);
    $relative_alias = substr($alias,$relative_alias_pos + 1);
    $primary_record = drush_sitealias_get_record($primary_alias);
    if (array_key_exists('root', $primary_record) && !array_key_exists('remote-host', $primary_record)) {
      drush_sitealias_create_sites_alias($primary_record['root']);
    }
    _drush_sitealias_find_and_load_alias($relative_alias, $primary_record);
    $result = drush_sitealias_get_record($relative_alias);
    if (!empty($result)) {
      if (array_key_exists('inherited', $result)) {
        $result = array_merge($primary_record, $result);
      }
      $result['name'] = $relative_alias;
      _drush_sitealias_cache_alias(substr($alias, 1), $result);
    }
  }
  else {
    // Only aliases--those named entities that begin with '@'--can be loaded this way.
    // We also skip any alias that has already been loaded.
    if ((substr($alias,0,1) == '@') && !array_key_exists($alias,$all_site_aliases)) {
      drush_log(dt('Load alias !alias', array('!alias' => $alias)));
      $aliasname = substr($alias,1);
      $result = _drush_sitealias_find_and_load_alias($aliasname, $alias_path_context);
      if (!empty($result)) {
        $alias_options = array('site-aliases' => array($aliasname => $result));
        _drush_sitealias_add_inherited_values($alias_options['site-aliases']);
        drush_set_config_special_contexts($alias_options);
      }
    }
  }

  return $result;
}

/**
 * Load every alias file that can be found anywhere in the
 * alias search path.
 */
function drush_sitealias_load_all($resolve_parent = TRUE) {
  $result = _drush_sitealias_find_and_load_alias(NULL);
  if (!empty($result) && ($resolve_parent == TRUE)) {
    // If any aliases were returned, then check for
    // inheritance and then store the aliases into the
    // alias cache
    _drush_sitealias_add_inherited_values($result);
    $alias_options = array('site-aliases' => $result);
    drush_set_config_special_contexts($alias_options);
  }
}

/**
 * Worker function called by _drush_sitealias_load_alias and
 * drush_sitealias_load_all.  Traverses the alias search path
 * and finds the specified alias record.
 *
 * @param $aliasname
 *   The name of the alias without the leading '@' (i.e. 'name')
 *   or NULL to load every alias found in every alias file.
 * @param $alias_path_context
 *   When looking up a relative alias, the alias path context is
 *   the primary alias that we will start our search from.
 * @return
 *   An empty array if nothing was loaded.  If $aliasname is
 *   not null, then the array returned is the alias record for
 *   $aliasname.  If $aliasname is NULL, then the array returned
 *   is a $kay => $value pair of alias names and alias records
 *   loaded.
 */
function _drush_sitealias_find_and_load_alias($aliasname, $alias_path_context = NULL) {
  $result = array();

  // The alias path is a list of folders to search for alias settings files
  $alias_path = drush_sitealias_alias_path($alias_path_context);

  // $alias_files contains a list of filename patterns
  // to search for.  We will find any matching file in
  // any folder in the alias path.  The directory scan
  // is not deep, though; only files immediately in the
  // search path are considered.
  $alias_files = array('/.*aliases\.drushrc\.php/');
  if ($aliasname == NULL) {
    $alias_files[] = '/.*\.alias\.drushrc\.php/';
  }
  else {
    $alias_files[] = '/' . preg_quote($aliasname) . '\.alias\.drushrc\.php/';
  }

  // Search each path in turn
  foreach ($alias_path as $path) {
    // Find all of the matching files in this location
    $alias_files_to_consider = array();
    foreach ($alias_files as $file_pattern_to_search_for) {
      $alias_files_to_consider = array_merge($alias_files_to_consider, array_keys(drush_scan_directory($path, $file_pattern_to_search_for, array('.', '..', 'CVS'), 0, FALSE)));
    }

    // For every file that matches, check inside it for
    // an alias with a matching name.
    foreach ($alias_files_to_consider as $filename) {
      if (file_exists($filename)) {
        $aliases = $options = array();
        include $filename;
        unset($options['site-aliases']); // maybe unnecessary

        // If $aliases are not set, but $options are, then define one alias named
        // after the first word of the file, before '.alias.drushrc.php.
        if (empty($aliases) && !empty($options)) {
          $this_alias_name = substr(basename($filename),0,strpos(basename($filename),'.'));
          $aliases[$this_alias_name] = $options;
          $options = array();
        }
        // If aliasname is NULL, then we will store
        // all $aliases into the alias cache
        if ($aliasname == NULL) {
          if (!empty($aliases)) {
            if (!empty($options)) {
              foreach ($aliases as $name => $value) {
                $aliases[$name] = array_merge($options, $value);
              }
              $options = array();
            }
            
            foreach ($aliases as $name => $value) {
              _drush_sitealias_initialize_alias_record($aliases[$name]);
            }
            
            $result = array_merge($result, $aliases);
          }
        }
        // If aliasname is not NULL, then we will store
        // only the named alias into the alias cache
        elseif ((isset($aliases)) && array_key_exists($aliasname, $aliases)) {
          drush_set_config_special_contexts($options); // maybe unnecessary
          $result = array_merge($options, $aliases[$aliasname]);
          _drush_sitealias_initialize_alias_record($result);
        }
      }
    }
  }

  return $result;
}

/**
 * Check to see if there is a 'parent' item in the alias; if there is,
 * then load the parent alias record and overlay the entries in the
 * current alias record on top of the items from the parent record.
 *
 * @param $aliases
 *   An array of alias records that are modified in-place.
 */
function _drush_sitealias_add_inherited_values(&$aliases) {
  foreach ($aliases as $alias_name => $alias_value) {
    if (array_key_exists('parent', $alias_value)) {
      // Prevent circular references from causing an infinite loop
      _drush_sitealias_cache_alias($alias_name, array());
      // Fetch and merge in each parent
      foreach (explode(',', $alias_value['parent']) as $parent) {
        $parent_record = drush_sitealias_get_record($parent);
        if ($parent_record != NULL) {
          unset($parent_record['name']);
          $aliases[$alias_name] = array_merge($parent_record, $aliases[$alias_name]);
        }
      }
      unset($aliases[$alias_name]['parent']);
    }
  }
}

/**
 * Add an empty record for the specified alias name
 *
 * @param $alias_name
 *   The name of the alias, without the leading "@"
 */
function _drush_sitealias_cache_alias($alias_name, $alias_record) {
  $cache =& drush_get_context('site-aliases');
  $cache["@$alias_name"] = $alias_record;
  
  // If the alias record points at a local site, make sure
  // that both the drupal root and the site folder for that site
  // are added to the alias path, so that other alias files
  // stored in those locations become searchable.
  if (!array_key_exists('remote-host', $alias_record) && array_key_exists('root', $alias_record)) {
    drush_sitealias_add_to_alias_path($alias_record['root']);
    $site_dir = drush_sitealias_local_site_path($alias_record);
    if (isset($site_dir)) {
      drush_sitealias_add_to_alias_path($site_dir);
    }
  }
}

/**
 * If the alias record does not contain a 'databases' or 'db-url'
 * entry, then use backend invoke to look up the settings value
 * from the remote or local site.  The 'db_url' form is preferred;
 * nothing is done if 'db_url' is not available (e.g. on a D7 site)
 *
 * @param $alias_record
 *   The full alias record to populate with database settings
 */
function drush_sitealias_add_db_url(&$alias_record) {
  if (!isset($alias_record['db-url']) && !isset($alias_record['databases']) && !isset($alias_record['site-list'])) {
    $values = drush_do_site_command($alias_record, "sql-conf", array(), array('db-url' => TRUE));
    if (isset($values['object']['db-url'])) {
      $alias_record['db-url'] = $values['object']['db-url'];
    }
  }
}

/**
 * Return the databases record from the alias record
 *
 * @param $alias_record
 *   A record returned from drush_sitealias_get_record
 * @returns
 *   A databases record (always in D7 format) or NULL
 *   if the databases record could not be found.
 */
function sitealias_get_databases_from_record(&$alias_record) {
  $altered_record = drush_sitealias_add_db_settings($alias_record);

  return array_key_exists('databases', $alias_record) ? $alias_record['databases'] : NULL;
}

/**
 * If the alias record does not contain a 'databases' or 'db-url'
 * entry, then use backend invoke to look up the settings value
 * from the remote or local site.  The 'databases' form is
 * preferred; 'db_url' will be converted to 'databases' if necessary.
 *
 * @param $alias_record
 *   The full alias record to populate with database settings
 */
function drush_sitealias_add_db_settings(&$alias_record)
{
  $altered_record = FALSE;

  // If the alias record does not have a defined 'databases' entry,
  // then we'll need to look one up
  if (!isset($alias_record['db-url']) && !isset($alias_record['databases']) && !isset($alias_record['site-list'])) {
    $values = drush_do_site_command($alias_record, "sql-conf", array(), array('all' => TRUE));
    if (isset($values['object'])) {
      $alias_record['databases'] = $values['object'];
      $altered_record = TRUE;
      // If the name is set, then re-cache the record after we fetch the databases
      if (array_key_exists('name', $alias_record)) {
        $all_site_aliases =& drush_get_context('site-aliases');
        $all_site_aliases[$alias_record['name']] = $alias_record;
      }
    }
  }

  return $altered_record;
}

/**
 * Walk through the path aliases and find any paths that begin with a '@'.
 * If found, query the 'status' function for the local or remote
 * site and use the result returned to populate the value of the
 * path entry.
 *
 * For example, if the path aliases contain an entry
 * '%files' => '@File_Directory_Path', this function will call
 * 'drush status' and will use the value of the line labeled
 * 'File Directory Path:' to populate the '%files' entry in
 * the path aliases.
 *
 * @param $alias_record
 *   The full alias record to use in path alias expansion
 * @param $test_string
 *   If not empty, only path alias items contained in the test
 *   string will be resolved.
 */
function drush_sitealias_resolve_path_references(&$alias_record, $test_string = '') {
  $resolution_needed = FALSE;
  // Check to see if we have any path values that begin with '@'
  // that also exist in the test string
  if (array_key_exists('path-aliases', $alias_record)) {
    foreach ($alias_record['path-aliases'] as $key => $value) {
      if (substr($value,0,1) == '@') {
        if (empty($test_string) || (strstr($test_string, $key) !== FALSE)) {
          $resolution_needed = TRUE;
        }
      }
    }
  }

  if ($resolution_needed) {
    $values = drush_do_site_command($alias_record, "status");
    $status_values = $values['object'];
    if (isset($status_values)) {
      foreach ($alias_record['path-aliases'] as $key => $value) {
        if (substr($value,0,1) == '@') {
          $status_key = strtr(substr($value,1), '-_', '  ');
          if (array_key_exists($status_key, $status_values)) {
            $alias_record['path-aliases'][$key] = $status_values[$status_key];
            drush_log(dt('Resolved path !key to !value', array('!key' => $status_key, '!value' => $status_values[$status_key])));
          }
          else {
            unset($alias_record['path-aliases'][$key]);
          }
        }
      }
    }
  }
}

/**
 * Given an alias record that is a site list (contains a 'site-list' entry),
 * resolve all of the members of the site list and return them
 * is an array of alias records.
 *
 * @param $alias_record
 *   The site list alias record array
 * @return
 *   An array of individual site alias records
 */
function drush_sitealias_resolve_sitelist($alias_record) {
  $result_list = array();
  if (isset($alias_record)) {
    if (array_key_exists('site-list', $alias_record)) {
      foreach ($alias_record['site-list'] as $sitespec) {
        $one_result = drush_sitealias_get_record($sitespec);
        $result_list = array_merge($result_list, drush_sitealias_resolve_sitelist($one_result));
      }
    }
    elseif (array_key_exists('name', $alias_record)) {
      $result_list[$alias_record['name']] = $alias_record;
    }
  }

  return $result_list;
}

/**
 * Check to see if the uri is the same in the source and target
 * lists for all items in the array.  This is a strong requirement
 * in D6; in D7, it is still highly convenient for the uri to
 * be the same, because the site folder name == the uri, and if
 * the uris match, then it is easier to rsync between remote machines.
 *
 * @param $source
 *   Array of source alias records
 * @param $target
 *   Array of target alias records to compare against source list
 * @return
 *   TRUE iff the uris of the sources and targets are in alignment
 */
function drush_sitealias_check_lists_alignment($source, $target) {
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

/**
 * If the source and target lists contain alias records to the same
 * sets of sites, but in different orders, this routine will re-order
 * the lists so that they are in alignment.
 *
 * TODO:  Review the advisability of this operation.
 */
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

/**
 * Initialize an alias record; called as soon as the alias
 * record is loaded from its alias file, before it is stored
 * in the cache.
 *
 * @param alias_record
 *   The alias record to be initialized; paramter is modified in place.
 */
function _drush_sitealias_initialize_alias_record(&$alias_record) {
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
      $alias_record['site-list'] = explode(',', $alias_record['site-list']);
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
  // Adjustments for aliases to drupal instances (as opposed to aliases that are site lists)
  if (array_key_exists('uri', $alias_record)) {
    // Make sure that there is always a 'path-aliases' array
    if (!array_key_exists('path-aliases', $alias_record)) {
      $alias_record['path-aliases'] = array();
    }
    // If there is a 'root' entry, then copy it to the '%root' path alias
    $alias_record['path-aliases']['%root'] = $alias_record['root'];
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
        $result = '#' . drush_sitealias_uri_to_site_dir($alias_record['uri']);
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
function _drush_find_local_sites_at_root($a_drupal_root = '', $search_depth = 1) {
  $site_list = array();
  $base_path = (empty($a_drupal_root) ? drush_get_context('DRUSH_DRUPAL_ROOT') : $a_drupal_root );
  if (drush_valid_drupal_root($base_path)) {
    // If $a_drupal_root is in fact a valid drupal root, then return
    // all of the sites found inside the 'sites' folder of this drupal instance.
    $site_list = _drush_find_local_sites_in_sites_folder($base_path);
  }
  else {
    $bootstrap_files = drush_scan_directory($base_path, '/' . basename(DRUSH_DRUPAL_BOOTSTRAP) . '/' , array('.', '..', 'CVS'), 0, drush_get_option('search-depth', $search_depth) + 1, 'filename', 1);
    foreach ($bootstrap_files as $one_bootstrap => $info) {
      $includes_dir = dirname($one_bootstrap);
      if (basename($includes_dir) == basename(dirname(DRUSH_DRUPAL_BOOTSTRAP))) {
        $drupal_root = dirname($includes_dir);
        $site_list = array_merge(_drush_find_local_sites_in_sites_folder($drupal_root), $site_list);
      }
    }
  }
  
  return $site_list;
}

/**
 * Return a list of all of the local sites at the specified 'sites' folder.
 */
function _drush_find_local_sites_in_sites_folder($a_drupal_root) {
  $site_list = array();
  
  // If anyone searches for sites at a given root, then
  // make sure that alias files stored at this root
  // directory are included in the alias search path
  drush_sitealias_add_to_alias_path($a_drupal_root);
  
  $base_path = $a_drupal_root . '/sites';
  
  // TODO:  build a cache keyed off of $base_path (realpath($base_path)?),
  // so that it is guarenteed that the lists returned will definitely be
  // exactly the same should this routine be called twice with the same path.

  $files = drush_scan_directory($base_path, '/settings\.php/', array('.', '..', 'CVS', 'all'), 0, 1, 'filename', 1);
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

function drush_sitealias_create_sites_alias($a_drupal_root = '') {
  $sites_list = _drush_find_local_sites_at_root($a_drupal_root);
  _drush_sitealias_cache_alias('sites', array('site-list' => $sites_list));
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
    if (!array_key_exists('%drush', $alias_record['path-aliases'])) {
      if (array_key_exists('%drush-script', $alias_record['path-aliases'])) {
        $alias_record['path-aliases']['%drush'] = dirname($alias_record['path-aliases']['%drush-script']);
      }
      else {
        $alias_record['path-aliases']['%drush'] = dirname($GLOBALS['argv'][0]);
      }
    }
    // Add the path to the site folder to the path aliases as !site
    if (!array_key_exists('%site', $alias_record['path-aliases']) && array_key_exists('uri', $alias_record)) {
      $alias_record['path-aliases']['%site'] = 'sites/' . drush_sitealias_uri_to_site_dir($alias_record['uri']) . '/';
    }
    // Add %files if it does not exist
    if (!array_key_exists('%files', $alias_record['path-aliases'])) {
      // Path values that start with '@' will call 'drush status'
      // to get the actual value to be substituted.
      $alias_record['path-aliases']['%files'] = '@File_Directory_Path';
    }
  }
}

/**
 * If '$alias' is the name of a folder in the sites folder of the given drupal
 * root, then build an alias record for it
 *
 * @param alias
 *   The name of the site in the 'sites' folder to convert
 * @return array
 *   An alias record, or empty if none found.
 */
function _drush_sitealias_find_record_for_local_site($alias, $drupal_root = null) {
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

  if (file_exists($site_settings_file)) {
    if (!isset($drupal_root)) {
      $drupal_root = drush_locate_root($site_settings_file);
    }

    $alias_record['root'] = $drupal_root;
    if (isset($alias)) {
      $alias_record['uri'] = $alias;
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
 * '%' or '!' are path aliases, the rest are entries in the alias record.
 */
function _drush_sitealias_set_record_element(&$alias_record, $key, $value) {
  if ((substr($key,0,1) == '%') || (substr($key,0,1) == '!')) {
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
    // Create an alias '@self'
    _drush_sitealias_cache_alias('self', $site_alias_settings);
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
function drush_sitealias_set_alias_context($site_alias_settings, $prefix = '') {
  $options = drush_get_context('alias');

  // backend invoke needs 'root' and 'uri' on the command line, so we will
  // handle these two specially
  $options_context = empty($prefix) ? array('root', 'uri') : array();
  // There are some items that we should just skip
  $skip_list = array('site-aliases', 'command-specific');
  // Transfer all options from the site alias to the drush options
  // in the 'alias' context.
  foreach ($site_alias_settings as $key => $value) {
    // Special handling for path aliases:
    if ($key == "path-aliases") {
      foreach (array('%drush-script', '%dump', '%include') as $path_key) {
        if (array_key_exists($path_key, $value)) {
          $options[$prefix . substr($path_key, 1)] = $value[$path_key];
        }
      }
    }
    elseif (in_array($key, $options_context)) {
      drush_set_option($prefix . $key, $value, 'options');
    }
    elseif (!in_array($key, $skip_list)) {
      $options[$prefix . $key] = $value;
    }
  }
  drush_set_config_options('alias', $options);
}
