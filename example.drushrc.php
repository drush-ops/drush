<?php
// $Id$

/*
 * Examples of valid statements for a drushrc.php file. Use this file to cut down on
 * typing of options and avoid mistakes.
 *
 * Rename this file to drushrc.php and optionally copy it to one of
 * four convenient places, listed below in order of precedence:
 *
 * - Drupal site folder.
 * - Drupal installation root.
 * - User Home folder (i.e. ~/.drushrc.php).
 * - Drush installation folder.
 *
 * If a configuration file is found in any of the above locations, it
 * will be loaded and merged with other configuration files in the
 * search list.
 *
 * Alternately, copy it to any location and load it with the --config (-c) option.
 * Note that this preempts loading any other configuration files!
 */

// Specify a particular multisite.
# $options['l'] = 'http://example.com/subir';

// Specify your Drupal core base directory (useful if you use symlinks).
# $options['r'] = '/home/USER/workspace/drupal-6';

// Specify CVS for checkouts
# $options['package-handler'] = 'cvs';

// Specify CVS credentials for checkouts (requires --package-handler=cvs)
# $options['cvscredentials'] = 'name:password';

// Specify additional directories to search for *.drush.inc files
// Use POSIX path separator (':')
# $options['i'] = 'sites/default:profiles/myprofile'; 

// Enable verbose mode.
# $options['v'] = 1; 


/*
 * Customize this associative array with your own tables. This is the 
 * list of tables that are entirely omitted by the 'sql dump' and 'sql load' 
 * commands when a skip-tables-key is provided. You may add new tables to the existing array or add a new 
 * element.
 */
$options['skip-tables'] = array(
 'common' => array('accesslog', 'cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'search_dataset', 'search_index', 'search_total', 'sessions', 'watchdog'),
);

/*
 * Customize this associative array with your own tables. This is the 
 * list of tables that whose *data* is skipped by the 'sql dump' and 'sql load' 
 * commands when a structure-tables-key is provided. You may add new tables to the existing array or add a new 
 * element.
 */
$options['structure-tables'] = array(
 'common' => array('accesslog', 'cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'search_dataset', 'search_index', 'search_total', 'sessions', 'watchdog'),
);

// Use cvs checkouts when downloading and updating modules.
// An example of a command specific argument being set in drushrc.php
// $options['package-handler'] = 'cvs';

// Specify additional directories to search for scripts
// Use POSIX path separator (':')
# $options['script-path'] = 'sites/all/scripts:profiles/myprofile/scripts';

/**
 * Variable overrides:
 *
 * To override specific entries in the 'variable' table for this site,
 * set them here. Any configuration setting from the 'variable'
 * table can be given a new value. We use the $override global here
 * to make sure that changes from settings.php can not wipe out these
 * settings.
 *
 * Remove the leading hash signs to enable.
 */
# $override = array(
#   'site_name' => 'My Drupal site',
#   'theme_default' => 'minnelli',
#   'anonymous' => 'Visitor',
# );
