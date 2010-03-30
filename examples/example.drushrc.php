<?php
// $Id$

/*
 * Examples of valid statements for a drushrc.php file. Use this file to cut down on
 * typing of options and avoid mistakes.
 *
 * Rename this file to drushrc.php and optionally copy it to one of
 * five convenient places, listed below in order of precedence:
 *
 * 1. Drupal site folder (e.g sites/{default|example.com}/drushrc.php).
 * 2. Drupal installation root.
 * 3. In any location, as specified by the --config (-c) option.
 * 4. User Home folder (i.e. ~/.drushrc.php).
 * 5. System wide configuration folder (e.g. /etc/drush/drushrc.php).
 * 6. Drush installation folder.
 *
 * If a configuration file is found in any of the above locations, it
 * will be loaded and merged with other configuration files in the
 * search list.
 *
 * IMPORTANT NOTE on configuration file loading:
 *
 * At its core, drush works by "bootstrapping" the Drupal environment
 * in very much the same way that is done during a normal page request
 * from the web server, so most drush commands run in the context
 * of a fully-initialized website.
 *
 * Configuration files are loaded in the reverse order they are
 * shown above.  Configuration files #6 through #3 are loaded immediately;
 * the configuration file stored in the Drupal root is loaded
 * when Drupal is initialized, and the configuration file stored
 * in the site folder is loaded when the site is initialized.
 *
 * This load order means that in a multi-site environment, the
 * configuration file stored in the site folder will only be
 * available for commands that operate on that one particular
 * site.  Additionally, there are some drush commands such as
 * pm-download do not bootstrap a drupal environment at all,
 * and therefore only have access to configuration files #6 - #3.
 * The drush commands 'rsync' and 'sql-sync' are special cases.
 * These commands will load the configuration file for the site
 * specified by the source parameter; however, they do not
 * load the configuration file for the site specified by the
 * destination parameter, nor do they load configuration files
 * for remote sites.
 */

// DEPRECATED:  Allow command names to contain spaces.
// This feature will be removed shortly; drush-3 will
// require commands to be named with dashes instead of
// spaces (e.g. "cache-clear" instead of "cache clear").
// During the transition period, uncomment the line below
// to allow commands with spaces to be used.
# $options['allow-spaces-in-commands'] = 1;

// Specify a particular multisite.
# $options['l'] = 'http://example.com/subir';

// Specify your Drupal core base directory (useful if you use symlinks).
# $options['r'] = '/home/USER/workspace/drupal-6';

// Load a drushrc.php configuration file from the current working directory.
# $options['c'] = '.';

// Specify CVS for checkouts
# $options['package-handler'] = 'cvs';

// Specify CVS credentials for checkouts (requires --package-handler=cvs)
# $options['cvscredentials'] = 'name:password';

// Specify additional directories to search for *.drush.inc files
// Use POSIX path separator (':')
# $options['i'] = 'sites/default:profiles/myprofile';

// Specify additional directories to search for *.alias.drushrc.php
// and *.aliases.drushrc.php files
# $options['alias-path'] = '/path/to/aliases:/path2/to/more/aliases';

// Specify directory where sql-sync will store persistent dump files.
// Keeping the dump files around will improve the performance of rsync
// when the database is rsync'ed to a remote system.  If a dump directory
// is not specified, then sql-sync will store dumps in temporary files.
# $options['dump-dir'] = '/path/to/dumpdir';

// Enable verbose mode.
# $options['v'] = 1;

// Default logging level for php notices.  Defaults to "notice"; set to "warning"
// if doing drush development.  Also make sure that error_reporting is set to E_ALL
// in your php configuration file.  See 'drush status' for the path to your php.ini file.
# $options['php-notices'] = 'warning';

// Specify options to pass to ssh in backend invoke. (Default is to prohibit password authentication; uncomment to change)
# $options['ssh-options'] = '-o PasswordAuthentication=no';

/*
 * Customize this associative array with your own tables. This is the list of
 * tables whose *data* is skipped by the 'sql-dump' and 'sql-sync' commands when
 * a structure-tables-key is provided. You may add new tables to the existing
 * array or add a new element.
 */
$options['structure-tables'] = array(
 'common' => array('cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'sessions', 'watchdog'),
);

/*
 * Customize this associative array with your own tables. This is the list of
 * tables that are entirely omitted by the 'sql-dump' and 'sql-sync' commands
 * when a skip-tables-key is provided. This is useful if your database contains
 * non Drupal tables used by some other application or during a migration for
 * example. You may add new tables to the existing array or add a new element.
 */
$options['skip-tables'] = array(
 'common' => array('migration_data1', 'migration_data2'),
);

/*
 * Command-specific options
 *
 * To define options that are only applicable to certain commands,
 * make an entry in the 'command-specific' structures as shown below.
 * The name of the command may be either the command's full name
 * or any of the command's aliases.
 *
 * Options defined here will be overridden by options of the same
 * name on the command line.  Unary flags such as "--verbose" are overridden
 * via special "--no-xxx" options (e.g. "--no-verbose").
 *
 * Limitation: If 'verbose' is set in a command-specific option,
 * it must be cleared by '--no-verbose', not '--no-v', and visa-versa.
 */
# $command_specific['rsync'] = array('verbose' => TRUE);
# $command_specific['dl'] = array('cvscredentials' => 'user:pass');

// Specify additional directories to search for scripts
// Use POSIX path separator (':')
# $options['script']['script-path'] = 'sites/all/scripts:profiles/myprofile/scripts';

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
