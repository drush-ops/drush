<?php

/**
 * @file
 * The site alias API.
 *
 * Run commands on remote server(s).
 * @see example.aliases.drushrc.php
 * @see http://drupal.org/node/670460
 */

use Drush\Log\LogLevel;

/**
 * Check to see if the user specified an alias
 * in an arguement, or via site-set.  If so, return
 * the name of the alias.
 *
 * If the alias came from args, then remove it
 * from args.
 */
function drush_sitealias_check_arg_and_site_set() {
  $args = drush_get_arguments();
  $target_alias = FALSE;

  // Test to see if the first arg is a valid alias identifier.
  // If the first arguement is a well-formed identifier, but we
  // cannot find a record for it, then we will fail with an error.
  if (!empty($args) && drush_sitealias_valid_alias_format($args[0])) {
    // Pop the alias off the arguments list first thing.
    $target_alias = array_shift($args);
    drush_set_arguments($args);
  }
  else {
    // If the user did not specify an alias via an argument,
    // check to see if a site env was set.
    $target_alias = drush_sitealias_site_get();
  }

  // Record the user's desired target alias name
  if ($target_alias) {
    drush_set_context('DRUSH_TARGET_SITE_ALIAS', $target_alias);
  }
  return $target_alias;
}

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
  if (!empty($args) && _drush_sitealias_set_context_by_name($args[0])) {
    drush_set_context('DRUSH_TARGET_SITE_ALIAS', $args[0]);
    array_shift($args);
    // We only need to expand the site specification
    // once, then we are done.
    drush_set_arguments($args);
    return TRUE;
  }
  // Return false to indicate that no site alias was specified.
  return FALSE;
}

/*
 * Check to see if user has selected a site via site-set command.
 */
function drush_sitealias_check_site_env() {
  $site = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
  if (empty($site)) {
    $site_env = drush_sitealias_site_get();
    if (!empty($site_env) && (_drush_sitealias_set_context_by_name($site_env))) {
      drush_set_context('DRUSH_TARGET_SITE_ALIAS', $site_env);
      return TRUE;
    }
  }
  // Return false to indicate that no site alias was specified.
  return FALSE;
}

/**
 * Check to see if a '@self' record was created during bootstrap.
 * If not, make one now.
 */
function drush_sitealias_create_self_alias() {
  $self_record = drush_sitealias_get_record('@self');
  if (!array_key_exists('root', $self_record) && !array_key_exists('remote-host', $self_record)) {
    $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
    $uri = drush_get_context('DRUSH_SELECTED_URI');
    if (!empty($drupal_root) && !empty($uri)) {
      // Create an alias '@self'
      _drush_sitealias_cache_alias('@self', array('root' => $drupal_root, 'uri' => $uri));
    }
  }
}

/**
 * Given a list of alias records, shorten the name used if possible
 */
function drush_sitealias_simplify_names($site_list) {
  $result = array();
  foreach ($site_list as $original_name => $alias_record) {
    $adjusted_name = $alias_record['#name'];
    $hashpos = strpos($original_name, '#');
    if ($hashpos !== FALSE) {
      $adjusted_name = substr($original_name, $hashpos);
      if (array_key_exists('remote-host', $alias_record)) {
        $adjusted_name = $alias_record['remote-host'] . $adjusted_name;
      }
    }
    $result[$adjusted_name] = $alias_record;
  }
  return $result;
}

/**
 * Given an array of site specifications, resolve each one in turn and
 * return an array of alias records.  If you only want a single record,
 * it is preferable to simply call drush_sitealias_get_record() directly.
 *
 * @param $site_specifications
 *   One of:
 *     A comma-separated list of site specifications: '@site1,@site2'
 *     An array of site specifications: array('@site1','@site2')
 *     An array of alias records:
 *       array(
 *         'site1' => array('root' => ...),
 *         'site2' => array('root' => ...)
 *       )
 *   An array of site specifications.
 *   @see drush_sitealias_get_record() for the format of site specifications.
 * @return
 *   An array of alias records
 */
function drush_sitealias_resolve_sitespecs($site_specifications, $alias_path_context = NULL) {
  $result_list = array();
  $not_found = array();
  if (!is_array($site_specifications)) {
    $site_specifications = explode(',', $site_specifications);
  }
  if (!empty($site_specifications)) {
    foreach ($site_specifications as $site) {
      if (is_array($site)) {
        $result_list[] = $site;
      }
      else {
        $alias_record = drush_sitealias_get_record($site, $alias_path_context);
        if (!$alias_record) {
          $not_found[] = $site;
        }
        else {
          $result_list = array_merge($result_list, drush_sitealias_resolve_sitelist($alias_record));
        }
      }
    }
  }
  return array($result_list, $not_found);
}

/**
 * Returns TRUE if $alias is a valid format for an alias name.
 *
 * Mirrors the allowed formats shown below for drush_sitealias_get_record.
 */
function drush_sitealias_valid_alias_format($alias) {
  return ( (strpos($alias, ',') !== false) ||
    ((strpos($alias, '@') === FALSE ? 0 : 1) + (strpos($alias, '/') === FALSE ? 0 : 1) + (strpos($alias, '#') === FALSE ? 0 : 1) >= 2) ||
    ($alias{0} == '#') ||
    ($alias{0} == '@')
  );
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
 * - /path/to/drupal#sitename
 * - user@server/path/to/drupal#sitename
 * - user@server/path/to/drupal            (sitename == server)
 * - user@server#sitename                  (only if $option['r'] set in some drushrc file on server)
 * - #sitename                             (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
 * - sitename                              (only if $option['r'] already set, and 'sitename' is a folder in $option['r']/sites)
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
function drush_sitealias_get_record($alias, $alias_context = NULL) {
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
    $alias_record = _drush_sitealias_get_record($alias, $alias_context);
  }
  if (!empty($alias_record)) {
    if (array_key_exists('#name', $alias_record)) {
      if ($alias_record['#name'] == 'self') {
        $path = drush_sitealias_local_site_path($alias_record);
        if ($path) {
          $cached_alias_record = drush_sitealias_lookup_alias_by_path($path);
          // Don't overrite keys which have already been negotiated.
          unset($cached_alias_record['#name'], $cached_alias_record['root'], $cached_alias_record['uri']);
          $alias_record = array_merge($alias_record, $cached_alias_record);
        }
      }
    }
    else {
      $alias_record['#name'] = drush_sitealias_uri_to_site_dir($alias);
    }
  }
  return $alias_record;
}

/**
 * This is a continuation of drush_sitealias_get_record, above.  It is
 * not intended to be called directly.
 */
function _drush_sitealias_get_record($alias, $alias_context = NULL) {
  $alias_record = array();
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
      if ((strpos($alias, '@') === FALSE ? 0 : 1) + ((strpos($alias, '/') === FALSE && strpos($alias, '\\') === FALSE) ? 0 : 1) + (strpos($alias, '#') === FALSE ? 0 : 1) >= 2) {
        if ((substr($alias,0,7) != 'http://') && !drush_is_absolute_path($alias)) {
          // Add on a scheme so that "user:pass@server" will always parse correctly
          $parsed = parse_url('http://' . $alias);
        }
        else if (drush_is_windows() && drush_is_absolute_path($alias)) {
          // On windows if alias begins with a filesystem path we must add file:// scheme to make it parse correcly
          $parsed = parse_url('file:///' . $alias);
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
          if (drush_valid_root($base) || ($relative_alias == '@sites')) {
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
    if (!isset($alias_record['remote']) && !isset($alias_record['#loaded-config'])) {
      if (array_key_exists('root', $alias_record)) {
        drush_sitealias_add_to_alias_path($alias_record['root'] . '/drush');
        drush_sitealias_add_to_alias_path($alias_record['root'] . '/sites/all/drush');
      }
      // TODO: We should probably remove this feature, and put it back
      // in, but in different places (e.g. site selection, sql-sync + rsync
      // parameters, etc.)
      $alias_site_dir = drush_sitealias_local_site_path($alias_record);

      if (isset($alias_site_dir)) {
        // Add the sites folder of this site to the alias search path list
        drush_sitealias_add_to_alias_path($alias_site_dir);
      }
      if (isset($alias_record['config']) && file_exists($alias_record['config'])) {
        drush_load_config_file('site', $alias_record['config']);
        $alias_record['#loaded-config'] = TRUE;
      }
      unset($alias_record['config']);
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
      $add_path = explode(PATH_SEPARATOR, $add_path);
    }
    // Normalize path to make sure we don't add the same path twice on
    // windows due to different spelling. e.g. c:\tmp and c:/tmp
    foreach($add_path as &$path) {
      $path = drush_normalize_path($path);
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
  $context_path = array();
  if (isset($alias_path_context)) {
    $context_path = array(drush_sitealias_local_site_path($alias_path_context));
  }
  // We get the current list of site paths by adding NULL
  // (nothing) to the path list, which is a no-op
  $site_paths = drush_sitealias_add_to_alias_path(NULL);

  // If the user defined the root of a drupal site, then also
  // look for alias files in /drush and /sites/all/drush.
  $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  if (!empty($drupal_root)) {
    $site_paths[] = drush_sitealias_alias_base_directory($drupal_root . '/../drush');
    $site_paths[] = drush_sitealias_alias_base_directory($drupal_root . '/drush');
    $site_paths[] = drush_sitealias_alias_base_directory($drupal_root . '/sites/all/drush');
    $uri = drush_get_context('DRUSH_SELECTED_URI');
    if (empty($uri)) {
      $uri = 'default';
    }
    $site_dir = drush_sitealias_uri_to_site_dir($uri, $drupal_root);
    if ($site_dir) {
      $site_paths[] = drush_sitealias_alias_base_directory("$drupal_root/sites/$site_dir");
    }
  }
  $alias_path = (array) drush_get_context('ALIAS_PATH', array());
  return array_unique(array_merge($context_path, $alias_path, $site_paths));
}

/**
 * If there is a directory 'site-aliases' in the specified search location,
 * then search ONLY in that directory for aliases.  Otherwise, search
 * anywhere inside the specified directory for aliases.
 */
function drush_sitealias_alias_base_directory($dir) {
  $potential_location = $dir . '/site-aliases';
  if (is_dir($potential_location)) {
    return $potential_location;
  }
  return $dir;
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

  if (isset($alias_record['root']) && !isset($alias_record['remote-host'])) {
    if (isset($alias_record['uri'])) {
      $uri = $alias_record['uri'];
      $uri = preg_replace('#^[^:]*://#', '', $uri);
      while (!$result && !empty($uri)) {
        if (file_exists($alias_record['root'] . '/sites/sites.php')) {
          $sites = array();
          include($alias_record['root'] . '/sites/sites.php');
          if (array_key_exists($uri, $sites)) {
            $result = $alias_record['root'] . '/sites/' . $sites[$uri];
          }
        }
        if (!$result) {
          $result = ($alias_record['root'] . '/sites/' . drush_sitealias_uri_to_site_dir($uri, drush_sitealias_get_root($alias_record)));
        }
        $result = realpath($result);
        $uri = preg_replace('#^[^.]*\.*#', '', $uri);
      }
    }
    if (!$result) {
      $result = realpath($alias_record['root'] . '/sites/default');
    }
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

  // Only aliases--those named entities that begin with '@'--can be loaded this way.
  // We also skip any alias that has already been loaded.
  if ((substr($alias,0,1) == '@') && !array_key_exists($alias,$all_site_aliases)) {
    $aliasname = substr($alias,1);
    $result = _drush_sitealias_find_and_load_alias($aliasname, $alias_path_context);
    if (!empty($result)) {
      $alias_options = array('site-aliases' => array($aliasname => $result));
      _drush_sitealias_add_inherited_values($alias_options['site-aliases']);
      drush_set_config_special_contexts($alias_options);
      if (array_key_exists('#file', $result)) {
        drush_log(dt('Loaded alias !alias from file !file', array('!alias' => $alias, '!file' => $result['#file'])));
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
  $result = _drush_sitealias_find_and_load_all_aliases();
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
 * @return
 *   An array of $kay => $value pair of alias names and alias records
 *   loaded.
 */
function _drush_sitealias_find_and_load_all_aliases() {
  $result = array();

  $drush_alias_files = _drush_sitealias_find_alias_files();
  drush_set_context('drush-alias-files', $drush_alias_files);

  // For every file that matches, check inside it for
  // an alias with a matching name.
  foreach ($drush_alias_files as $filename) {
    if (file_exists($filename)) {
      $aliases = $options = array();
      // silently ignore files we can't include
      if ((@include $filename) === FALSE) {
        drush_log(dt('Cannot open alias file "!alias", ignoring.', array('!alias' => realpath($filename))), LogLevel::BOOTSTRAP);
        continue;
      }
      unset($options['site-aliases']); // maybe unnecessary

      // If $aliases are not set, but $options are, then define one alias named
      // after the first word of the file, before '.alias.drushrc.php.
      if (empty($aliases) && !empty($options)) {
        $this_alias_name = substr(basename($filename),0,strpos(basename($filename),'.'));
        $aliases[$this_alias_name] = $options;
        $options = array();
      }
      // If this is a group alias file, then make an
      // implicit alias from the group name that contains
      // a site-list of all of the aliases in the file
      $group_name = '';
      if (substr($filename, -20) == ".aliases.drushrc.php") {
        $group_name = basename($filename,".aliases.drushrc.php");
        if (!empty($aliases) && !array_key_exists($group_name, $aliases)) {
          $alias_names = array();
          foreach (array_keys($aliases) as $one_alias) {
            $alias_names[] = "@$group_name.$one_alias";
            $aliases["$group_name.$one_alias"] = $aliases[$one_alias];
            unset($aliases[$one_alias]);
          }
          $aliases[$group_name] = array('site-list' => implode(',', $alias_names));
        }
      }
      if (!empty($aliases)) {
        if (!empty($options)) {
          foreach ($aliases as $name => $value) {
            $aliases[$name] = array_merge($options, $value);
          }
          $options = array();
        }

        foreach ($aliases as $name => $value) {
          _drush_sitealias_initialize_alias_record($aliases[$name]);
          $aliases[$name]['#name'] = $name;
          $aliases[$name]['#file'] = $filename;
        }

        $result = _sitealias_array_merge($result, $aliases);
        // If we found at least one alias from this file
        // then record it in the drush-alias-files context.
        $drush_alias_files = drush_get_context('drush-alias-files');
        if (!in_array($filename, $drush_alias_files)) {
          $drush_alias_files[] = $filename;
        }
        drush_set_context('drush-alias-files', $drush_alias_files);
      }
    }
  }

  return $result;
}

/**
 * Function to find all alias files that might contain aliases
 * that match the requested alias name.
 */
function _drush_sitealias_find_alias_files($aliasname = NULL, $alias_path_context = NULL) {
  $alias_files_to_consider = array();

  // The alias path is a list of folders to search for alias settings files
  $alias_path = drush_sitealias_alias_path($alias_path_context);

  // $alias_files contains a list of filename patterns
  // to search for.  We will find any matching file in
  // any folder in the alias path.  The directory scan
  // is not deep, though; only files immediately in the
  // search path are considered.
  $alias_files = array('/.*aliases\.drush(' . DRUSH_MAJOR_VERSION . '|)rc\.php$/');
  if ($aliasname == NULL) {
    $alias_files[] = '/.*\.alias\.drush(' . DRUSH_MAJOR_VERSION . '|)rc\.php$/';
  }
  else {
    $alias_files[] = '/' . preg_quote($aliasname, '/') . '\.alias\.drush(' . DRUSH_MAJOR_VERSION . '|)rc\.php$/';
  }

  // Do not scan into the files directory.
  $blacklist = array_merge(array('files'), drush_filename_blacklist());

  // Search each path in turn.
  foreach ($alias_path as $path) {
    // Find all of the matching files in this location
    foreach ($alias_files as $file_pattern_to_search_for) {
      drush_log(dt('Scanning into @path for @pattern', array('@path' => $path, '@pattern' => $file_pattern_to_search_for)), LogLevel::DEBUG_NOTIFY);
      $alias_files_to_consider = array_merge($alias_files_to_consider, array_keys(drush_scan_directory($path, $file_pattern_to_search_for, $blacklist, 0, TRUE)));
    }
  }

  return $alias_files_to_consider;
}

/**
 * Traverses the alias search path and finds the specified alias record.
 *
 * @param $aliasname
 *   The name of the alias without the leading '@' (i.e. '#name')
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
  // Special checking for '@sites' alias
  if ($aliasname == 'sites') {
    $drupal_root = NULL;
    if ($alias_path_context != null) {
      if (array_key_exists('root', $alias_path_context) && !array_key_exists('remote-host', $alias_path_context)) {
        $drupal_root = $alias_path_context['root'];
      }
    }
    else {
      $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
    }
    if (isset($drupal_root) && !is_array($drupal_root)) {
      drush_sitealias_create_sites_alias($drupal_root);
    }
  }

  $alias_files_to_consider = _drush_sitealias_find_alias_files($aliasname, $alias_path_context);

  return _drush_sitealias_find_and_load_alias_from_file($aliasname, $alias_files_to_consider);
}

function _drush_sitealias_find_and_load_alias_from_file($aliasname, $alias_files_to_consider) {
  $result = array();
  $result_names = array();

  // For every file that matches, check inside it for
  // an alias with a matching name.
  $recorded_files = array();
  foreach ($alias_files_to_consider as $filename) {
    if (file_exists($filename)) {
      $aliases = $options = array();
      // silently ignore files we can't include
      if ((@include $filename) === FALSE) {
        drush_log(dt('Cannot open alias file "!alias", ignoring.', array('!alias' => realpath($filename))), LogLevel::BOOTSTRAP);
        continue;
      }
      unset($options['site-aliases']); // maybe unnecessary

      // If $aliases are not set, but $options are, then define one alias named
      // after the first word of the file, before '.alias.drushrc.php.
      if (empty($aliases) && !empty($options)) {
        $this_alias_name = substr(basename($filename),0,strpos(basename($filename),'.'));
        $aliases[$this_alias_name] = $options;
        $options = array();
      }
      // If this is a group alias file, then make an
      // implicit alias from the group name that contains
      // a site-list of all of the aliases in the file
      $group_prefix = '';
      if (substr($filename, -20) == ".aliases.drushrc.php") {
        $group_name = basename($filename,".aliases.drushrc.php");
        $group_prefix = $group_name . '.';
        if (!empty($aliases) && !array_key_exists($group_name, $aliases)) {
          $alias_names = array();
          foreach (array_keys($aliases) as $one_alias) {
            $alias_names[] = "@$group_name.$one_alias";
            $aliases[$one_alias]['#name'] = "$group_name.$one_alias";
            $aliases[$one_alias]['#group'] = $group_name;
            $aliases["$group_name.$one_alias"] = $aliases[$one_alias];
            $aliases[$one_alias]["#hidden"] = TRUE;
          }
          $aliases[$group_name] = array('site-list' => implode(',', $alias_names), '#group' => $group_name, '#name' => $group_name);
        }
      }
      // Store only the named alias into the alias cache
      if ((isset($aliases)) && !empty($aliasname) && array_key_exists($aliasname, $aliases)) {
        drush_set_config_special_contexts($options); // maybe unnecessary
        $one_result = array_merge($options, $aliases[$aliasname]);
        $one_result['#file'] = $filename;
        if (!array_key_exists('#name', $one_result)) {
          $one_result['#name'] = $aliasname;
        }
        _drush_sitealias_initialize_alias_record($one_result);
        // If the alias name is exactly the same as a previous match, then
        // merge the two records together
        if (!empty($result) && ($result['#name'] == $one_result['#name'])) {
          $result = _sitealias_array_merge($result, $one_result);
        }
        // Add the name of the found record to the list of results
        else {
          $result_names[] = "@" . $one_result['#name'];
          $result = $one_result;
        }
      }
    }
  }
  // If there are multiple matches, then return a list of results.
  if (count($result_names) > 1) {
    $result = array('site-list' => $result_names);
  }

  return $result;
}

/**
 * Merges two site aliases.
 *
 * array_merge_recursive is too much; we only want to run
 * array_merge on the common top-level keys of the array.
 *
 * @param array $site_alias_a
 *   A site alias array.
 * @param array $site_alias_b
 *   A site alias array.
 * @return
 *   A site alias array where the keys from $site_alias_a are overwritten by the
 *   keys from $site_alias_b.
 */
function _sitealias_array_merge($site_alias_a, $site_alias_b) {
  $result = $site_alias_a;

  foreach($site_alias_b as $key => $value) {
    if (is_array($value) && array_key_exists($key, $result)) {
      $result[$key] = array_merge($result[$key], $value);
    }
    else {
      $result[$key] = $value;
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
    // Prevent circular references from causing an infinite loop
    _drush_sitealias_cache_alias("@$alias_name", array());
    _drush_sitealias_add_inherited_values_to_record($alias_value);
    $aliases[$alias_name] = $alias_value;
  }
}

function _drush_sitealias_add_inherited_values_to_record(&$alias_value) {
  drush_command_invoke_all_ref('drush_sitealias_alter', $alias_value);
  if (isset($alias_value['parent'])) {
    drush_log(dt("Using deprecated 'parent' element '!parent' in '!name'.", array('!parent' => $alias_value['parent'], '!name' => $alias_value['#name'])), LogLevel::DEBUG);
    // Fetch and merge in each parent
    foreach (explode(',', $alias_value['parent']) as $parent) {
      $parent_record = drush_sitealias_get_record($parent);
      unset($parent_record['#name']);
      unset($parent_record['#file']);
      unset($parent_record['#hidden']);
      $array_based_keys = array_merge(drush_get_special_keys(), array('path-aliases'));
      foreach ($array_based_keys as $array_based_key) {
        if (isset($alias_value[$array_based_key]) && isset($parent_record[$array_based_key])) {
          $alias_value[$array_based_key] = array_merge($parent_record[$array_based_key], $alias_value[$array_based_key]);
        }
      }
      $alias_value = array_merge($parent_record, $alias_value);
    }
  }
  unset($alias_value['parent']);
}

/**
 * Add an empty record for the specified alias name
 *
 * @param $alias_name
 *   The name of the alias, including the leading "@"
 */
function _drush_sitealias_cache_alias($alias_name, $alias_record) {
  $cache =& drush_get_context('site-aliases');
  // If the alias already exists in the cache, then merge
  // the new alias with the existing alias
  if (array_key_exists($alias_name, $cache)) {
    $alias_record = array_merge($cache[$alias_name], $alias_record);
  }
  if (!isset($alias_record['#name'])) {
    $alias_record['#name'] = trim($alias_name, '@');
  }
  $cache[$alias_name] = $alias_record;

  // If the alias record points at a local site, make sure
  // that /drush, /sites/all/drush and the site folder for that site
  // are added to the alias path, so that other alias files
  // stored in those locations become searchable.
  if (!array_key_exists('remote-host', $alias_record) && !empty($alias_record['root'])) {
    drush_sitealias_add_to_alias_path($alias_record['root'] . '/drush');
    drush_sitealias_add_to_alias_path($alias_record['root'] . '/sites/all/drush');
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
    drush_sitealias_add_db_settings($alias_record);
  }
  if (!isset($alias_record['db-url']) && isset($alias_record['databases'])) {
    $alias_record['db-url'] = drush_sitealias_convert_databases_to_db_url($alias_record['databases']);
  }
}

/**
 * Drush still accepts --db-url format database specifications as
 * cli parameters; it is therefore useful to be able to convert
 * from a database record back to a db-url sometimes.
 */
function drush_sitealias_convert_db_spec_to_db_url($db_spec) {
  $result = urlencode($db_spec["driver"]) . "://";
  if (isset($db_spec["username"])) {
    $result .= urlencode($db_spec["username"]);
    if (isset($db_spec["password"])) {
      $result .= ":" . urlencode($db_spec["password"]);
    }
    $result .= "@";
  }
  // Host is required, unless this is an sqlite db.
  if (isset($db_spec["host"])) {
    $result .= urlencode($db_spec["host"]);
    if (isset($db_spec["port"])) {
      $result .= ":" . urlencode($db_spec["port"]);
    }
    $result .= '/' . urlencode($db_spec["database"]);
  }
  else {
    // URL-encode the database, but convert slashes
    // back to their original form for readability.
    // This portion is the "path" of the URL, so it may
    // contain slashes.  This is important for sqlite.
    $result .= str_replace("%2F", "/", urlencode(ltrim($db_spec["database"], '/')));
  }
  return $result;
}

/**
 * Create a db-url from the databases record.
 */
function drush_sitealias_convert_databases_to_db_url($databases) {
  if ((count($databases) == 1) && isset($databases['default'])) {
    $result = drush_sitealias_convert_db_spec_to_db_url($databases['default']['default']);
  }
  else {
    foreach ($databases as $key => $db_info) {
      $result[$key] = drush_sitealias_convert_db_spec_to_db_url($db_info['default']);
    }
  }
  return $result;
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
 * Return the $db_spec record for the database associated with
 * the provided alias record.  @see drush_sitealias_add_db_settings(),
 * which will be used to first add the database information to the
 * alias records, invoking sql-conf to look them up if necessary.
 *
 * The options 'database' and 'target' are used to specify which
 * specific database should be fetched from the database record;
 * they may appear in the alias definition, or may be taken from the
 * command line options.  The values 'default' and 'default' are
 * used if these options are not specified in either location.
 *
 * Note that in the context of sql-sync, the site alias record will
 * be taken from one of the source or target aliases
 * (e.g. `drush sql-sync @source @target`), which will be overlayed with
 * any options that begin with 'source-' or 'target-', respectively.
 * Therefore, the commandline options 'source-database' and 'source-target'
 * (or 'target-database' and 'source-target') may also affect the operation
 * of this function.
 */
function drush_sitealias_get_db_spec(&$alias_record, $default_to_self = FALSE, $prefix = '') {
  $db_spec = NULL;
  $databases = sitealias_get_databases_from_record($alias_record);
  if (isset($databases) && !empty($databases)) {
    $database = drush_sitealias_get_option($alias_record, 'database', 'default', $prefix);
    $target = drush_sitealias_get_option($alias_record, 'target', 'default', $prefix);
    if (array_key_exists($database, $databases) && array_key_exists($target, $databases[$database])) {
      $db_spec = $databases[$database][$target];
    }
  }
  elseif ($default_to_self) {
    $db_spec = _drush_sql_get_db_spec();
  }

  if (isset($db_spec)) {
    $remote_host = drush_sitealias_get_option($alias_record, 'remote-host', NULL, $prefix);
    if (!drush_is_local_host($remote_host)) {
      $db_spec['remote-host'] = $remote_host;
      $db_spec['port'] = drush_sitealias_get_option($alias_record, 'remote-port', (isset($db_spec['port']) ? $db_spec['port'] : NULL), $prefix);
    }
  }

  return $db_spec;
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
function drush_sitealias_add_db_settings(&$alias_record) {
  $altered_record = FALSE;
  if (isset($alias_record['root'])) {
    // If the alias record does not have a defined 'databases' entry,
    // then we'll need to look one up
    if (!isset($alias_record['db-url']) && !isset($alias_record['databases']) && !isset($alias_record['site-list'])) {
      $values = drush_invoke_process($alias_record, "sql-conf", array(), array('all' => TRUE), array('integrate' => FALSE, 'override-simulated' => TRUE));
      if (is_array($values) && ($values['error_status'] == 0)) {
        $altered_record = TRUE;
        // If there are any special settings in the '@self' record returned by drush_invoke_process,
        // then add those into our altered record as well
        if (array_key_exists('self', $values)) {
          $alias_record = array_merge($values['self'], $alias_record);
        }
        drush_sitealias_cache_db_settings($alias_record, $values['object']);
      }
    }
  }
  return $altered_record;
}

function drush_sitealias_cache_db_settings(&$alias_record, $databases) {
  if (!empty($databases)) {
    $alias_record['databases'] = $databases;
  }

  // If the name is set, then re-cache the record after we fetch the databases
  if (array_key_exists('#name', $alias_record)) {
    $all_site_aliases =& drush_get_context('site-aliases');
    $all_site_aliases['@' . $alias_record['#name']] = $alias_record;
    // Check and see if this record is a copy of 'self'
    if (($alias_record['#name'] != 'self') && array_key_exists('@self', $all_site_aliases) && array_key_exists('#name', $all_site_aliases['@self']) && ($all_site_aliases['@self']['#name'] == $alias_record['#name'])) {
      $all_site_aliases['@self'] = $alias_record;
    }
  }
}

/**
 * Check to see if we have already bootstrapped to a site.
 */
function drush_sitealias_is_bootstrapped_site($alias_record) {
  if (!isset($alias_record['remote-host']) && array_key_exists('root', $alias_record)) {
    $self_record = drush_sitealias_get_record("@self");
    if (empty($self_record) || !array_key_exists('root', $self_record)) {
      // TODO:  If we have not bootstrapped to a site yet, we could
      // perhaps bootstrap to $alias_record here.
      return FALSE;
    }
    elseif(($alias_record['root'] == $self_record['root']) && ($alias_record['uri'] == $self_record['uri'])) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Determines whether a given site alias is for a remote site.
 *
 * @param string $alias
 *   An alias name or site specification.
 *
 * @return bool
 *   Returns TRUE if the alias refers to a remote site, FALSE if it does not, or NULL is unsure.
 */
function drush_sitealias_is_remote_site($alias) {
  if (is_array($alias) && !empty($alias['remote-host'])) {
    return TRUE;
  }
  if (!is_string($alias) || !strlen($alias)) {
    return NULL;
  }

  $site_record = drush_sitealias_get_record($alias);
  if ($site_record) {
    if (!empty($site_record['remote-host'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
  else {
    drush_set_error('Unrecognized site alias.');
  }
}

/**
 * Get the name of the current bootstrapped site
 */
function drush_sitealias_bootstrapped_site_name() {
  $site_name = NULL;
  $self_record = drush_sitealias_get_record('@self');
  if (array_key_exists('#name', $self_record)) {
    $site_name = $self_record['#name'];
  }
  if (!isset($site_name) || ($site_name == '@self')) {
    $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
    if (isset($drupal_root)) {
      $drupal_uri = drush_get_context('DRUSH_SELECTED_URI', 'default');
      $drupal_uri = str_replace('http://', '', $drupal_uri);
      // TODO: Maybe use _drush_sitealias_find_local_alias_name?
      $site_name = $drupal_root . '#' . $drupal_uri;
    }
  }
  return $site_name;
}

/**
 * If there are any path aliases (items beginning with "%") in the test
 * string, then resolve them as path aliases and add them to the provided
 * alias record.
 *
 * @param $alias_record
 *   The full alias record to use in path alias expansion
 * @param $test_string
 *   A slash-separated list of path aliases to resolve
 *   e.g. "%files/%special".
 */
function drush_sitealias_resolve_path_references(&$alias_record, $test_string = '') {
  $path_aliases = array_key_exists('path-aliases', $alias_record) ? $alias_record['path-aliases'] : array();
  // Convert the test string into an array of items, and
  // from this make a comma-separated list of projects
  // that we can pass to 'drush status'.
  $test_array = explode('/', $test_string);
  $project_array = array();
  foreach($test_array as $one_item) {
    if (!empty($one_item) && ($one_item[0] == '%') && (!array_key_exists($one_item,$path_aliases))) {
      $project_array[] = substr($one_item,1);
    }
  }
  $project_list = implode(',', $project_array);

  if (!empty($project_array)) {
    // Optimization:  if we're already bootstrapped to the
    // site specified by $alias_record, then we can just
    // call _core_site_status_table() rather than use backend invoke.
    if (drush_sitealias_is_bootstrapped_site($alias_record) && drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $status_values = _core_site_status_table($project_list);
    }
    else {
      $values = drush_invoke_process($alias_record, "core-status", array(), empty($project_list) ? array() : array('project' => $project_list), array('integrate' => FALSE, 'override-simulated' => TRUE));
      $status_values = $values['object'];
    }
    if (isset($status_values['%paths'])) {
      foreach ($status_values['%paths'] as $key => $path) {
        $alias_record['path-aliases'][$key] = $path;
      }
    }
    // If 'root' is not set in the alias, then fill it in from the status values.
    if (!isset($alias_record['root']) && isset($status_values['root'])) {
      $alias_record['root'] = $status_values['root'];
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
    elseif (array_key_exists('#name', $alias_record)) {
      $result_list[$alias_record['#name']] = $alias_record;
    }
  }

  return $result_list;
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
 *   The alias record to be initialized; parameter is modified in place.
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
  else {
    if (isset($alias_record['root']) && !isset($alias_recort['uri'])) {
      $alias_recort['uri'] = 'default';
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
    if (isset($alias_record['root'])) {
      $alias_record['path-aliases']['%root'] = $alias_record['root'];
    }
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
        $result = '#' . drush_sitealias_uri_to_site_dir($alias_record['uri'], drush_sitealias_get_root($alias_record));
      }
      // There should always be a root
      if (array_key_exists('root', $alias_record)) {
        $result = $alias_record['root'] . $result;
      }
      if (array_key_exists('remote-host', $alias_record)) {
        $result = drush_remote_host($alias_record) . $result;
      }

      // Add the database info to the specification if desired
      if ($with_db) {
        // If db-url is not supplied, look it up from the remote
        // or local site and add it to the site alias
        if (!isset($alias_record['db-url'])) {
          drush_sitealias_add_db_url($alias_record);
        }
        $result = $result . '?db-url=' . urlencode(is_array($alias_record['db-url']) ? $alias_record['db-url']['default'] : $alias_record['db-url']);
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
  if (!empty($base_path)) {
    if (drush_valid_root($base_path)) {
      // If $a_drupal_root is in fact a valid drupal root, then return
      // all of the sites found inside the 'sites' folder of this drupal instance.
      $site_list = _drush_find_local_sites_in_sites_folder($base_path);
    }
    else {
      $bootstrap_files = drush_scan_directory($base_path, '/' . basename(DRUSH_DRUPAL_SIGNATURE) . '/' , array('.', '..', 'CVS', 'examples'), 0, drush_get_option('search-depth', $search_depth) + 1, 'filename', 1);
      foreach ($bootstrap_files as $one_bootstrap => $info) {
        $includes_dir = dirname($one_bootstrap);
        if (basename($includes_dir) == basename(dirname(DRUSH_DRUPAL_SIGNATURE))) {
          $drupal_root = dirname($includes_dir);
          $site_list = array_merge(_drush_find_local_sites_in_sites_folder($drupal_root), $site_list);
        }
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
  _drush_sitealias_cache_alias('@sites', array('site-list' => $sites_list));
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
        $alias_record['path-aliases']['%drush'] = dirname(drush_find_drush());
      }
    }
    // Add the path to the site folder to the path aliases as !site
    if (!array_key_exists('%site', $alias_record['path-aliases']) && array_key_exists('uri', $alias_record)) {
      $alias_record['path-aliases']['%site'] = 'sites/' . drush_sitealias_uri_to_site_dir($alias_record['uri'], drush_sitealias_get_root($alias_record)) . '/';
    }
  }
}

/**
 * Find the name of a local alias record that has the specified
 * root and uri.
 */
function _drush_sitealias_find_local_alias_name($root, $uri) {
  $result = '';
  $all_site_aliases =& drush_get_context('site-aliases');

  foreach ($all_site_aliases as $alias_name => $alias_values) {
    if (!array_key_exists('remote-host', $alias_values) && array_key_exists('root', $alias_values) && array_key_exists('uri', $alias_values) && ($alias_name != '@self')) {
      if (($root == $alias_values['root']) && ($uri == $alias_values['uri'])) {
        $result = $alias_name;
      }
    }
  }

  return $result;
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
function _drush_sitealias_find_record_for_local_site($alias, $drupal_root = NULL) {
  $alias_record = array();

  // Clip off the leading '#' if it is there
  if (substr($alias,0,1) == '#') {
    $alias = substr($alias,1);
  }

  if (!isset($drupal_root)) {
    $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  }

  if (!empty($drupal_root)) {
    $alias_dir = drush_sitealias_uri_to_site_dir($alias, $drupal_root);
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
function drush_sitealias_uri_to_site_dir($uri, $site_root = NULL) {
  $uri = str_replace('http://', '', $uri);
  $uri = str_replace('https://', '', $uri);
  if (drush_is_windows()) {
    // Handle absolute paths on windows
    $uri = str_replace(array(':/', ':\\'), array('.', '.'), $uri);
  }

  $hostname = str_replace(array('/', ':', '\\'), array('.', '.', '.'), $uri);

  // Check sites.php mappings
  $site_dir = drush_site_dir_lookup_from_hostname($hostname, $site_root);

  return $site_dir ? $site_dir : $hostname;
}

/**
 * Convert from an old-style database URL to an array of database settings.
 *
 * @param db_url
 *   A Drupal 6 db url string to convert, or an array with a 'default' element.
 * @return array
 *   An array of database values containing only the 'default' element of
 *   the db url. If the parse fails the array is empty.
 */
function drush_convert_db_from_db_url($db_url) {
  $db_spec = array();

  if (is_array($db_url)) {
    $db_url_default = $db_url['default'];
  }
  else {
    $db_url_default = $db_url;
  }

  // If it's a sqlite database, pick the database path and we're done.
  if (strpos($db_url_default, 'sqlite://') === 0) {
    $db_spec = array(
      'driver'   => 'sqlite',
      'database' => substr($db_url_default, strlen('sqlite://')),
    );
  }
  else {
    $url = parse_url($db_url_default);
    if ($url) {
      // Fill in defaults to prevent notices.
      $url += array(
        'scheme' => NULL,
        'user'   => NULL,
        'pass'   => NULL,
        'host'   => NULL,
        'port'   => NULL,
        'path'   => NULL,
      );
      $url = (object)array_map('urldecode', $url);
      $db_spec = array(
        'driver'   => $url->scheme == 'mysqli' ? 'mysql' : $url->scheme,
        'username' => $url->user,
        'password' => $url->pass,
        'host' => $url->host,
        'port' => $url->port,
        'database' => ltrim($url->path, '/'),
      );
    }
  }

  return $db_spec;
}

/**
 * Convert from an old-style database URL to an array of database settings
 *
 * @param db_url
 *   A Drupal 6 db-url string to convert, or an array with multiple db-urls.
 * @return array
 *   An array of database values.
 */
function drush_sitealias_convert_db_from_db_url($db_url) {
  $result = array();

  if (!is_array($db_url)) {
    $result = array('default' => array('default' => drush_convert_db_from_db_url($db_url)));
  }
  else {
    foreach ($db_url as $one_name => $one_db_url) {
      $result[$one_name] = array('default' => drush_convert_db_from_db_url($one_db_url));
    }
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
  if ($alias) {
    $site_alias_settings = drush_sitealias_get_record($alias);
    if (!empty($site_alias_settings)) {
      drush_sitealias_set_alias_context($site_alias_settings, $prefix);
      drush_sitealias_cache_alias_by_path($site_alias_settings);
      if (empty($prefix)) {

        // Create an alias '@self'
        // Allow 'uri' from the commandline to override
        $drush_uri = drush_get_option(array('uri', 'l'), FALSE);
        if ($drush_uri) {
          $site_alias_settings['uri'] = $drush_uri;
        }

        _drush_sitealias_cache_alias('@self', $site_alias_settings);
        // Change the selected site to match the new --root and --uri, if any were set.
        _drush_preflight_root_uri();
      }
      return $site_alias_settings;
    }
  }
  return array();
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
 * First return an option set via drush_sitealias_overlay_options, if
 * any, then fall back on "%" . $option from the path aliases.
 */
function drush_sitealias_get_path_option($site_alias_record, $option, $default = NULL) {
  if (isset($site_alias_record) && array_key_exists($option, $site_alias_record)) {
    return $site_alias_record[$option];
  }
  if (isset($site_alias_record) && array_key_exists('path-aliases', $site_alias_record) && array_key_exists("%$option", $site_alias_record['path-aliases'])) {
    return $site_alias_record['path-aliases']["%$option"];
  }
  else {
    return drush_get_option($option, $default);
  }
}

/**
 * Given a site alias record, copy selected fields from it
 * into the drush 'alias' context.  The 'alias' context has
 * lower precedence than the 'cli' context, so values
 * set by an alias record can be overridden by command-line
 * parameters.
 *
 * @param site_alias_settings
 *   An alias record
 * @param prefix
 *   The prefix value to affix to the beginning of every
 *   key set.  For example, if this function is called once with
 *   'source-' and again with 'destination-' prefixes, then the
 *   source database records will be stored in 'source-databases',
 *   and the destination database records will be in
 *   'destination-databases'.
 */
function drush_sitealias_set_alias_context($site_alias_settings, $prefix = '') {
  $options = drush_get_context('alias');

  // There are some items that we should just skip
  $skip_list = drush_get_special_keys();
  // If 'php-options' are set in the alias, then we will force drush
  // to redispatch via the remote dispatch mechanism even if the target is localhost.
  if ((array_key_exists('php-options', $site_alias_settings) || array_key_exists('php', $site_alias_settings)) && !drush_get_context('DRUSH_BACKEND', FALSE)) {
    if (!array_key_exists('remote-host', $site_alias_settings)) {
      $site_alias_settings['remote-host'] = 'localhost';
    }
  }
  // If 'php-options' are not set in the alias, then skip 'remote-host'
  // and 'remote-user' if 'remote-host' is actually the local machine.
  // This prevents drush from using the remote dispatch mechanism (the command
  // is just run directly on the local machine, bootstrapping to the specified alias)
  elseif (array_key_exists('remote-host', $site_alias_settings) && drush_is_local_host($site_alias_settings['remote-host'])) {
    $skip_list[] = 'remote-host';
    $skip_list[] = 'remote-user';
  }
  // If prefix is set, then copy from the 'prefix-' version
  // of the drush special keys ('command-specific', 'path-aliases')
  // into the ordinary version.  This will allow us to set
  // 'source-command-specific' options that will only apply when
  // the alias is used as the source option for rsync or sql-sync.
  if (!empty($prefix)) {
    $special_contexts = drush_get_special_keys();
    foreach ($special_contexts as $option_name) {
      if (array_key_exists($prefix . $option_name, $site_alias_settings)) {
        $site_alias_settings[$option_name] = array_key_exists($option_name, $site_alias_settings) ? array_merge($site_alias_settings[$option_name], $site_alias_settings[$prefix . $option_name]) : $site_alias_settings[$prefix . $option_name];
      }
    }
  }
  // Transfer all options from the site alias to the drush options
  // in the 'alias' context.
  foreach ($site_alias_settings as $key => $value) {
    // Special handling for path aliases:
    if ($key == "path-aliases") {
      $path_aliases = $value;
      foreach (array('%drush-script', '%dump', '%dump-dir', '%include') as $path_key) {
        if (array_key_exists($path_key, $path_aliases)) {
          // Evaluate the path value, and substitute any path references found.
          // ex: '%dump-dir' => '%root/dumps' will store sql-dumps in the folder
          // 'dumps' in the Drupal root folder for the site.
          $evaluated_path = str_replace(array_keys($path_aliases), array_values($path_aliases), $path_aliases[$path_key]);
          $options[$prefix . substr($path_key, 1)] = $evaluated_path;
        }
      }
    }
    // Special handling for command-specific
    elseif ($key == "command-specific") {
      $options[$key] = $value;
    }
    elseif (!in_array($key, $skip_list)) {
      $options[$prefix . $key] = $value;
    }
  }
  drush_set_config_options('alias', $options);
}

/**
 * Call prior to drush_sitealias_evaluate_path to insure
 * that any site-specific aliases associated with any
 * local site in $path are defined.
 */
function _drush_sitealias_preflight_path($path) {
  $alias = NULL;
  // Parse site aliases if there is a colon in the path
  // We allow:
  //   @alias:/path
  //   machine.domain.com:/path
  //   machine:/path
  // Note that paths in the form "c:/path" are converted to
  // "/cygdrive/c/path" later; we do not want them to confuse
  // us here, so we skip paths that start with a single character
  // before the colon if we are running on Windows.  Single-character
  // machine names are allowed in Linux only.
  $colon_pos = strpos($path, ':');
  if ($colon_pos > (drush_is_windows("LOCAL") ? 1 : 0)) {
    $alias = substr($path, 0, $colon_pos);
    $path = substr($path, $colon_pos + 1);
    $site_alias_settings = drush_sitealias_get_record($alias);
    if (empty($site_alias_settings) && (substr($path,0,1) == '@')) {
      return NULL;
    }
    $machine = $alias;
  }
  else {
    $machine = '';
    // if the path is a site alias or a local site...
    $site_alias_settings = drush_sitealias_get_record($path);
    if (empty($site_alias_settings) && (substr($path,0,1) == '@')) {
      return NULL;
    }
    if (!empty($site_alias_settings) || drush_is_local_host($path)) {
      $alias = $path;
      $path = '';
    }
  }
  return array('alias' => $alias, 'path' => $path, 'machine' => $machine);
}

/**
 * Given a properly-escaped options string, replace any occurance of
 * %files and so on embedded inside it with its corresponding path.
 */
function drush_sitealias_evaluate_paths_in_options($option_string) {
  $path_aliases = _core_path_aliases();
  return str_replace(array_keys($path_aliases), array_values($path_aliases), $option_string);
}

/**
 * Evaluate a path from its shorthand form to a literal path
 * usable by rsync.
 *
 * A path is "machine:/path" or "machine:path" or "/path" or "path".
 * 'machine' might instead be an alias record, or the name
 * of a site in the 'sites' folder.  'path' might be (or contain)
 * '%root' or some other path alias.  This function will examine
 * all components of the path and evaluate them as necessary to
 * come to the final path.
 *
 * @param path
 *   The path to evaluate
 * @param additional_options
 *   An array of options that overrides whatever was passed in on
 *   the command line (like the 'process' context, but only for
 *   the scope of this one call).
 * @param local_only
 *   If TRUE, force an error if the provided path points to a remote
 *   machine.
 * @param os
 *   This should be the local system os, unless evaluate path is
 *   being called for rsync, in which case it should be "CWRSYNC"
 *   if cwrsync is being used, or "rsync" to automatically select
 *   between "LOCAL" and "CWRSYNC" based on the platform.
 * @return
 *   The site record for the machine specified in the path, if any,
 *   with the path to pass to rsync (including the machine specifier)
 *   in the 'evaluated-path' item.
 */
function drush_sitealias_evaluate_path($path, &$additional_options, $local_only = FALSE, $os = NULL, $command_specific_prefix = '') {
  $site_alias_settings = array();
  $path_aliases = array();
  $remote_user = '';

  $preflight = _drush_sitealias_preflight_path($path);
  if (!isset($preflight)) {
    return NULL;
  }

  $alias = $preflight['alias'];
  $path = $preflight['path'];
  $machine = $preflight['machine'];

  if (isset($alias)) {
    // Note that the alias settings may have an 'os' component, but we do
    // not want to use it here.  The paths passed to rsync should always be
    // escaped per the LOCAL rules, without regard to the remote platform type.
    $site_alias_settings = drush_sitealias_get_record($alias);
    if (!empty($command_specific_prefix)) {
      drush_command_set_command_specific_options($command_specific_prefix);
      drush_sitealias_command_default_options($site_alias_settings, $command_specific_prefix);
    }
  }

  if (!empty($site_alias_settings)) {
    if ($local_only && array_key_exists('remote-host', $site_alias_settings)) {
      return drush_set_error('DRUSH_REMOTE_SITE_IN_LOCAL_CONTEXT', dt("A remote site alias was used in a context where only a local alias is appropriate."));
    }

    // Apply any options from this alias that might affect our rsync
    drush_sitealias_set_alias_context($site_alias_settings);

    // Use 'remote-host' from settings if available; otherwise site is local
    if (drush_sitealias_is_remote_site($site_alias_settings)) {
      $machine = drush_remote_host($site_alias_settings);
    }
    else {
      $machine = '';
    }
  }
  else {
    // Strip the machine portion of the path if the
    // alias points to the local machine.
    if (drush_is_local_host($machine)) {
      $machine = '';
    }
    else {
      $machine = "$remote_user$machine";
    }
  }

  // TOD:  The code below is a little rube-goldberg-ish, and needs to be
  // reworked.  core-rsync will call this function twice: once to
  // evaluate the destination, and then again to evaluate the source.  Things
  // get odd with --exclude-paths, especially in conjunction with command-specific
  // and the --exclude-files option.  @see testCommandSpecific()

  // If the --exclude-other-sites option is specified, then
  // convert that into --include-paths='%site' and --exclude-sites.
  if (drush_get_option_override($additional_options, 'exclude-other-sites', FALSE) && !drush_get_context('exclude-other-sites-processed', FALSE)) {
    $include_path_option = drush_get_option_override($additional_options, 'include-paths', '');
    $additional_options['include-paths'] = '%site';
    if (!empty($include_path_option)) {
      // We use PATH_SEPARATOR here because we are later going to explicitly explode() this variable using PATH_SEPARATOR.
      $additional_options['include-paths'] .= PATH_SEPARATOR . $include_path_option;
    }
    $additional_options['exclude-sites'] = TRUE;
    drush_set_context('exclude-other-sites-processed', TRUE);
  }
  else {
    unset($additional_options['include-paths']);
  }
  // If the --exclude-files option is specified, then
  // convert that into --exclude-paths='%files'.
  if (drush_get_option_override($additional_options, 'exclude-files', FALSE) && !drush_get_option_override($additional_options, 'exclude-files-processed', FALSE, 'process')) {
    $exclude_path_option = drush_get_option_override($additional_options, 'exclude-paths', '');
    $additional_options['exclude-paths'] = '%files';
    if (!empty($exclude_path_option)) {
      // We use PATH_SEPARATOR here because we are later going to explicitly explode() this variable using PATH_SEPARATOR.
      $additional_options['exclude-paths'] .= PATH_SEPARATOR . $exclude_path_option;
    }
    $additional_options['exclude-files-processed'] = TRUE;
  }
  else {
    unset($additional_options['exclude-paths']);
  }

  // If there was no site specification given, and the
  // machine is local, then try to look
  // up an alias record for the default drush site.
  if (empty($site_alias_settings) && empty($machine)) {
    $drush_uri = drush_get_context('DRUSH_SELECTED_URI', 'default');
    $site_alias_settings = drush_sitealias_get_record($drush_uri);
  }

  // Always add transient defaults
  _drush_sitealias_add_transient_defaults($site_alias_settings);

  // The $resolve_path variable is used by drush_sitealias_resolve_path_references
  // to test to see if there are any path references such as %site or %files
  // in it, so that resolution is only done if the path alias is referenced.
  // Therefore, we can concatenate without worrying too much about the structure of
  // this variable's contents.
  $include_path = drush_get_option_override($additional_options, 'include-paths', '');
  $exclude_path = drush_get_option_override($additional_options, 'exclude-paths', '');
  if (is_array($include_path)) {
    $include_path = implode('/', $include_path);
  }
  if (is_array($exclude_path)) {
    $include_path = implode('/', $exclude_path);
  }
  $resolve_path = "$path/$include_path/$exclude_path";
  // Resolve path aliases such as %files, if any exist in the path
  if (!empty($resolve_path)) {
    drush_sitealias_resolve_path_references($site_alias_settings, $resolve_path);
  }

  if (array_key_exists('path-aliases', $site_alias_settings)) {
    $path_aliases = $site_alias_settings['path-aliases'];
  }

  // Get the 'root' setting from the alias; if it does not
  // exist, then get the root from the bootstrapped site.
  if (array_key_exists('root', $site_alias_settings)) {
    $drupal_root = $site_alias_settings['root'];
  }
  elseif (!drush_sitealias_is_remote_site($site_alias_settings)) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  }
  if (empty($drupal_root)) {
    $drupal_root = '';
  }
  else {
    // Add a slash to the end of the drupal root, as below.
    $drupal_root = drush_trim_path($drupal_root) . "/";
  }
  $full_path_aliases = $path_aliases;
  foreach ($full_path_aliases as $key => $value) {
    // Expand all relative path aliases to be based off of the Drupal root
    if (!drush_is_absolute_path($value, "LOCAL") && ($key != '%root')) {
      $full_path_aliases[$key] = $drupal_root . $value;
    }
    // We do not want slashes on the end of our path aliases.
    $full_path_aliases[$key] = drush_trim_path($full_path_aliases[$key]);
  }

  // Fill in path aliases in the path, the include path and the exclude path.
  $path = str_replace(array_keys($full_path_aliases), array_values($full_path_aliases), $path);
  if (!empty($include_path)) {
    drush_set_option('include-paths', str_replace(array_keys($path_aliases), array_values($path_aliases), $include_path));
  }
  if (!empty($exclude_path)) {
    drush_set_option('exclude-paths', str_replace(array_keys($path_aliases), array_values($path_aliases), $exclude_path));
  }
  // Next make the rsync path, which includes the machine
  // and path components together.
  // First make empty paths or relative paths start from the drupal root.
  if (empty($path) || (!drush_is_absolute_path($path, "LOCAL"))) {
    $path = $drupal_root . $path;
  }
  // When calculating a path for use with rsync, we must correct
  // absolute paths in the form c:\path when cwrsync is in use.
  $path = drush_correct_absolute_path_for_exec($path, $os);

  // If there is a $machine component, to the path, then
  // add it to the beginning
  $evaluated_path = drush_escapeshellarg($path, $os);
  if (!empty($machine)) {
    $evaluated_path = $machine . ':' . $evaluated_path;
  }

  //
  // Add our result paths:
  //
  //    evaluated-path:         machine:/path
  //    server-component:       machine
  //    path-component:         :/path
  //    path:                   /path
  //    user-path:              path (as specified in input parameter)
  //
  $site_alias_settings['evaluated-path'] = $evaluated_path;
  if (!empty($machine)) {
    $site_alias_settings['server-component'] = $machine;
  }
  $site_alias_settings['path-component'] = (!empty($path) ? ':' . $path : '');
  $site_alias_settings['path'] = $path;
  $site_alias_settings['user-path'] = $preflight['path'];

  return $site_alias_settings;
}

/**
 * Option keys used for site selection.
 */
function drush_sitealias_site_selection_keys() {
  return array('remote-host', 'remote-user', 'ssh-options', '#name', 'os');
}


function sitealias_find_local_drupal_root($site_list) {
  $drupal_root = NULL;

  foreach ($site_list as $site) {
    if (($drupal_root == NULL) && (array_key_exists('root', $site) && !array_key_exists('remote-host', $site))) {
      $drupal_root = $site['root'];
    }
  }

  return $drupal_root;
}


/**
 * Helper function to obtain the keys' names that need special handling in certain
 * cases.
 * @return
 *   A non-associative array containing the needed keys' names.
 */
function drush_get_special_keys() {
  $special_keys = array(
    'command-specific',
    'site-aliases',
  );
  return $special_keys;
}

/**
 * Read the tmp file where the persistent site setting is stored.
 *
 * @return string
 *   A valid site specification.
 */
function drush_sitealias_site_get() {
  if (($filename = drush_sitealias_get_envar_filename()) && file_exists($filename)) {
    $site = file_get_contents($filename);
    return $site;
  }
  else {
    return FALSE;
  }
}

/**
 * Un-set the currently use'd site alias.
 */
function drush_sitealias_site_clear() {
  if ($filename = drush_sitealias_get_envar_filename()) {
    return drush_delete_dir($filename);
  }
  return FALSE;
}

/**
 * Returns the filename for the file that stores the DRUPAL_SITE variable.
 *
 * @param string $filename_prefix
 *   An arbitrary string to prefix the filename with.
 *
 * @return string|false
 *   Returns the full path to temp file if possible, or FALSE if not.
 */
function drush_sitealias_get_envar_filename($filename_prefix = 'drush-drupal-site-') {
  $shell_pid = getenv('DRUSH_SHELL_PID');
  if (!$shell_pid && function_exists('posix_getppid')) {
    $shell_pid = posix_getppid();
  }
  if (!$shell_pid) {
    return FALSE;
  }

  $tmp = getenv('TMPDIR') ? getenv('TMPDIR') : '/tmp';
  $username = drush_get_username();

  return "{$tmp}/drush-env-{$username}/{$filename_prefix}" . $shell_pid;
}

/**
 * Cache the specified alias in the alias path cache.  The
 * alias path cache creates a lookup from the site folder
 * (/path/to/drupal/sites/default) to the provided alias record.
 *
 * Only the name of the alias and the path to the file it
 * is stored in is cached; when it is retrieved, it is
 * loaded directly from the correct file.
 */
function drush_sitealias_cache_alias_by_path($alias_record) {
  if (!isset($alias_record['remote-host']) && isset($alias_record['root']) && isset($alias_record['uri']) && isset($alias_record['#name']) && isset($alias_record['#file'])) {
    $path = drush_sitealias_local_site_path($alias_record);
    if ($path) {
      $cid = drush_get_cid('alias-path-', array(), array($path));
      $alias_path_data = array(
        '#name' => $alias_record['#name'],
        '#file' => $alias_record['#file'],
      );
      drush_cache_set($cid, $alias_path_data);
    }
  }
}

/**
 * Look for a defined alias that points to the specified
 * site directory.  The cache is tested first; if nothing
 * is cached, then an exhaustive search is done for the
 * specified site.  If the exhaustive search returns a
 * match, then it is cached.
 *
 * @param $path
 *   /path/to/drupal/sites/default
 * @return
 *   An alias record for the provided path
 */
function drush_sitealias_lookup_alias_by_path($path, $allow_best_match=FALSE) {
  $result = drush_sitealias_quick_lookup_cached_alias_by_path($path);
  $fallback = array();
  if (empty($result)) {
    $aliases = _drush_sitealias_find_and_load_all_aliases();
    foreach ($aliases as $name => $alias_record) {
      if (!isset($alias_record['remote-host']) && isset($alias_record['root']) && isset($alias_record['uri']) && isset($alias_record['#name']) && isset($alias_record['#file'])) {
        if ($path == drush_sitealias_local_site_path($alias_record)) {
          $result = $alias_record;
          break;
        }
        if (substr($path, 0, strlen($alias_record['root'])) == $alias_record['root']) {
          $fallback = $alias_record;
        }
      }
    }
  }
  if (empty($result) && $allow_best_match) {
    $result = $fallback;
  }
  if (!empty($result)) {
    _drush_sitealias_add_inherited_values_to_record($result);
    drush_sitealias_cache_alias_by_path($result);
  }
  return $result;
}

/**
 * Look for a cached alias that points to the specified
 * site directory.  Nothing is returned if there is no
 * matching cached alias.
 *
 * @param $path
 *   /path/to/drupal/sites/default
 * @return
 *   An alias record for the provided path
 */
function drush_sitealias_quick_lookup_cached_alias_by_path($path) {
  $alias_record = array();
  $cid = drush_get_cid('alias-path-', array(), array($path));
  $alias_path_cache = drush_cache_get($cid);
  if (isset($alias_path_cache->data)) {
    $alias_name = $alias_path_cache->data['#name'];
    $alias_file = $alias_path_cache->data['#file'];

    $alias_record = _drush_sitealias_find_and_load_alias_from_file($alias_name, array($alias_file));
    _drush_sitealias_add_inherited_values_to_record($alias_record);
    $alias_record['#name'] = $alias_name;
  }
  return $alias_record;
}

/**
 * Return the site root, if there is one in the record.
 */
function drush_sitealias_get_root($alias_record) {
  return array_key_exists('root', $alias_record) ? $alias_record['root'] : NULL;
}

/**
 * Decide on which side to run a core-rsync.
 *
 * @param $source
 * @param $destination
 * @param $runner Where to run the rsync operation: 'destination', 'source',
 *   'auto' ('destination' if both are remote, otherwise '@self') or FALSE (@self)
 * @return mixed
 */
function drush_get_runner($source, $destination, $runner = FALSE) {
  if (is_string($source)) {
    $source = drush_sitealias_get_record($site);
  }
  if (is_string($destination)) {
    $destination = drush_sitealias_get_record($destination);
  }

  // If both sites are remote, and --runner=auto, then we'll use the destination site.
  if (drush_sitealias_is_remote_site($source) && drush_sitealias_is_remote_site($destination)) {
    if ($runner == 'auto') {
      $runner = 'destination';
    }
  }

  // If the user explicitly requests a remote site, then return the selected one.
  if ($runner == 'destination') {
    return "@" . $destination['#name'];
  }
  if ($runner == 'source') {
    return "@" . $source['#name'];
  }

  // Default to running rsync locally. When in doubt, local is best, because
  // we can always resolve aliases here.
  return '@self';
}
