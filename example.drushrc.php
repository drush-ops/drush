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

// enable simulation mode
# $options['s'] = 1;

// specify a particular multisite
# $options['l'] = 'http://example.com/subir';

// specify your Drupal core base directory (useful if you use symlinks)
# $options['r'] = '/home/USER/workspace/drupal-6';

// Specify additional directories to search for *.drush.inc files
// Use POSIX path separator (':')
# $options['i'] = 'sites/default:profiles/myprofile'; 

// enable verbose mode
# $options['v'] = 1; 


/*
 * Customize this associative array with your own tables. This is the 
 * list of tables that are omitted by the 'sql dump' and 'sql load' 
 * commands when a skip is requested. You may add new tables to the existing array or add a new 
 * element.
 */
$options['skip-tables'] = array(
 'common' => array('accesslog', 'cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'search_dataset', 'search_index', 'search_total', 'sessions', 'watchdog'),
);

// use cvs checkouts when installing modules
// an example of a command specific argument being set in drushrc.php
// $options['handler'] = 'cvs';

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
