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
