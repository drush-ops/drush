<?php
// $Id$

/*
 * Examples of valid statements for a drushrc.php file. Use this file to cut down on
 * typing of options and avoid mistakes.
 *
 * Rename this file to drushrc.php and
 * optionally copy it to one of 5 convenient places. See drush_load_rc().
 */

// enable simulation mode
// $options['s'] = 1;

// specify a particular multisite
// $options['l'] = 'http://example.com/subir';

// enable verbose mode
// $options['v'] = 1; 

/*
 * Uncomment and customize this list with your own tables. This is the 
 * list of tables that are omitted by the 'sql dump' and 'sql load' 
 * commands when a skip is requested.
 */
# $options['skip-tables'] = array('accesslog', 'cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'search_dataset', 'search_index', 'search_total', 'sessions', 'watchdog');

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
