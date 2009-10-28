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
    // once, then we are done.  (Should we set a flag indicating
    // that we already did this?  Otherwise 'drush alias1 alias2 command'
    // would behave strangely.)
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
  // Sometimes getting an alias record involves loading settings.php or calling
  // backend invoke.  We'll cache all alias records fetched by this routine to
  // insure that we never have to do anything like that twice.
  $alias_record = drush_get_option('sitealias-' . $alias, array(), 'sitealias-cache');
  if (empty($alias_record)) {
    // Check to see if the provided parameter is in fact a defined alias.
    $all_site_aliases = drush_get_option('site-aliases', array());
    if (array_key_exists($alias, $all_site_aliases)) {
      $alias_record = $all_site_aliases[$alias];
      // If the alias record does not have a defined 'databases' entry,
      // then we'll need to look one up
      if ($db_settings_needed && !isset($alias_record['db-url']) && !isset($alias_record['databases'])) {
        // Check to see if we've cached the databases record from a previous invocation of this function
        $alias_record['databases'] = drush_get_option('databases-' . $alias, NULL, 'sitealias-cache');
        if (!isset($alias_record['databases'])) {
          // If the alias record is remote, then we'll use backend_invoke to fetch the
          // database settings from the remote machine
          if (array_key_exists('remote-host', $alias_record)) {
            $data = array('root' => $alias_record['path-aliases']['!root'], 'uri' => $alias_record['uri'], 'all' => TRUE);
            $args = array();
            $values = drush_backend_invoke_args("sql conf", $args, $data, 'GET', FALSE, $alias_record['path-aliases']['!drush-script'], $alias_record['remote-host'], $alias_record['remote-user']);
            $alias_record['databases'] = $values['object'];
          }
          // If the alias record is for a local machine, then look up and
          // use the database settings from the appropriate settings.php file.
          else {
            $alias_record = array_merge(_drush_sitealias_build_record_from_settings($alias), $alias_record);
          }
          // Cache our result so we don't need to do IPC for successive calls to this function
          drush_set_option('databases-' . $alias, $alias_record['databases'], 'sitealias-cache');
  }
      }
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
        // Copy various parts of the parsed URL into the appropriate records of the alias record
        foreach (array('user' => 'remote-user', 'pass' => 'remote-pass', 'host' => 'remote-host', 'fragment' => 'uri', 'path' => '!root') as $url_key => $option_key) {
          if (array_key_exists($url_key, $parsed)) {
            _drush_sitealias_set_record_element($alias_record, $option_key, $parsed[$url_key]);
          }
        }
        // If the site specification has a query, also set the query items
        // in the alias record.  This allows passing db_url as part of the
        // site specification, for example.
        foreach (explode('&', $parsed['query']) as $query_arg) {
          $query_components = explode('=', $query_arg);
          _drush_sitealias_set_record_element($alias_record, urldecode($query_components[0]), urldecode($query_components[1]));
        }

        // Case 3.): If the URL contains a 'host' portion but no fragment, then set the uri to the host
        // Note: We presume that 'server' is the best default for case 3; without this code, the default would
        // be whatever is set in $options['l'] on the target machine's drushrc.php settings file.
        if (array_key_exists('host', $parsed) && !array_key_exists('fragment', $parsed)) {
          $alias_record['uri'] = $parsed['host'];
        }
      }
      else {
        // Case 5.) and 6.):
        // If the alias is the name of a folder in the 'sites' directory, 
        // then use it as a local site specification.
        $alias_record = _drush_sitealias_find_record_for_local_site($alias, $db_settings_needed);
      }
    }
    _drush_sitealias_add_static_defaults($alias_record);
    // Fail fast if database settings are not available and the caller
    // said that they are required
    if ($db_settings_needed && !isset($alias_record['databases'])) {
      drush_print("Error: could not get database spec when it was required for " . $alias);
      exit(1);
    }
    drush_set_option('sitealias-' . $alias, $alias_record, 'sitealias-cache');
  }
  return $alias_record;
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
    $alias_record['databases'] = array('default' => array('default' => drush_convert_db_from_db_url($alias_record['db-url'])));
  }
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
    if (!array_key_exists('!drush', $alias_record['path-aliases'])) {
      $alias_record['path-aliases']['!drush'] = dirname(DRUSH_COMMAND);
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
  
  // Clip off the leading '#' if it is there
  if (substr($alias,0,1) == '#') {
    $alias = substr($alias,1);
  }
  
  // This function may be called during the drush bootstrap
  // (i.e., from drush_sitealias_check_arg()), when 'DRUSH_DRUPAL_ROOT'
  // has not been set.  It may also be called after bootstraping
  // has finished (e.g. to process an argument from sync or sql sync)
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
  
  // If we did not find an alias record that matches,
  // then build one.
  if (empty($alias_record)) {
    $site_settings_file = $drupal_root . '/sites/' . $alias . '/settings.php';
    if (isset($drupal_root) && file_exists($site_settings_file)) {
      $alias_record['uri'] = $alias;
      global $databases, $db_url;
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
    }
  }
  return $alias_record;
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
  $url = parse_url($db_url);
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
  // Transfer all non-array options from the site alias to the drush options
  // in the 'alias' context.
  foreach ($site_alias_settings as $key => $value) {
    if (!is_array($value) || ($key == "databases")) {
      drush_set_option($prefix . $key, $value, 'alias');
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
