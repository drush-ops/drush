<?php

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
 * 4. User's .drush folder (i.e. ~/.drush/drushrc.php).
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
 *
 * See `drush topic docs-bootstrap` for more information on how
 * bootstrapping affects the loading of drush configuration files.
 */

// Specify a particular multisite.
# $options['l'] = 'http://example.com/subir';

// Specify your Drupal core base directory (useful if you use symlinks).
# $options['r'] = '/home/USER/workspace/drupal-6';

// Load a drushrc.php configuration file from the current working directory.
# $options['c'] = '.';

// You should not use drush-4.x on Windows; upgrade to the 5.x branch.
// If you are really sure that you want to ignore this advice, you may
// still disable the warning by setting the 'check_os' setting to the
// special value shown below.
# $options['check_os'] = 'i-want-4.x';

// Control automatically check for updates in pm-updatecode and drush version.
// FALSE = never check for updates.  'head' = allow updates to drush-HEAD.
// TRUE (default) = allow updates to latest stable release.
# $options['self-update'] = FALSE;

// By default, drush will download projects compatibile with the
// current version of Drupal, or, if no Drupal site is specified,
// then the Drupal-7 version of the project is downloaded.  Set
// default-major to select a different default version.
# $options['default-major'] = 6;

// Specify CVS for checkouts
# $options['package-handler'] = 'cvs';

// Specify CVS credentials for checkouts (requires --package-handler=cvs)
# $options['cvscredentials'] = 'name:password';

// Specify additional directories to search for *.drush.inc files
// Separate by : (Unix-based systems) or ; (Windows).
# $options['i'] = 'sites/default:profiles/myprofile';

// Specify additional directories to search for *.alias.drushrc.php
// and *.aliases.drushrc.php files
# $options['alias-path'] = '/path/to/aliases:/path2/to/more/aliases';

// Specify directory where sql-sync will store persistent dump files.
// Keeping the dump files around will improve the performance of rsync
// when the database is rsync'ed to a remote system.  If a dump directory
// is not specified, then sql-sync will store dumps in temporary files.
# $options['dump-dir'] = '/path/to/dumpdir';

// Specify directory where sql-dump should store backups of database
// dumps.  @DATABASE is replaced with the name of the database being
// dumped, and @DATE is replaced with the current time and date of the
// dump.  TRUE will cause sql-dump to use the same backup directory that
// pm-updatecode does.
//
// If set, this can be explicitly overridden by specifying --result-file
// on the commandline.  The default behavior of dumping to
// STDOUT can be achieved via --result-file=0
# $options['result-file'] = '/path/to/backup/dir/@DATABASE_@DATE.sql';
# $options['result-file'] = TRUE;

// Enable verbose mode.
# $options['v'] = 1;

// Show database passwords in 'status' and 'sql-conf' commands
# $options['show-passwords'] = 1;

// Default logging level for php notices.  Defaults to "notice"; set to "warning"
// if doing drush development.  Also make sure that error_reporting is set to E_ALL
// in your php configuration file.  See 'drush status' for the path to your php.ini file.
# $options['php-notices'] = 'warning';

// Specify options to pass to ssh in backend invoke. (Default is to prohibit password authentication; uncomment to change)
# $options['ssh-options'] = '-o PasswordAuthentication=no';

// rsync version 2.6.8 or earlier will give an error message:
// "--remove-source-files: unknown option".  To fix this, set
// $options['rsync-version'] = '2.6.8'; (replace with the lowest
// version of rsync installed on any system you are using with
// drush).  Note that drush requires at least rsync version 2.6.4
// for some functions to work correctly.
// 
// Note that this option can also be set in a site alias.  This
// is preferable if newer versions of rsync are available on some
// of the systems you use.
// See: http://drupal.org/node/955092
# $options['rsync-version'] = '2.6.9';

/*
* The output charset suitable to pass to iconv PHP function as out_charset
* parameter. Drush will convert its output from UTF-8 to the charset specified
* here. It is possible to use //TRANSLIT and //IGNORE charset name suffixes
* (see iconv documentation). If not defined conversion will not be performed.
*/
# $options['output_charset'] = 'ISO-8859-1';
# $options['output_charset'] = 'KOI8-R//IGNORE';
# $options['output_charset'] = 'ISO-8859-1//TRANSLIT';

/*
 * Multiple command execution options
 */
// By default, drush will prepend the name of the
// site to the output of any multiple-site command 
// execution.  To disable this behavior, set the
// --no-label option
# $options['no-label'] = TRUE;

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
// Separate by : (Unix-based systems) or ; (Windows).
# $command_specific['script']['script-path'] = 'sites/all/scripts:profiles/myprofile/scripts';

// Always show release notes when running pm-update or pm-updatecode
# $command_specific['pm-update'] = array('notes' => TRUE);
# $command_specific['pm-updatecode'] = array('notes' => TRUE);

// List of drush commands or aliases that should override built-in 
// shell functions and commands; otherwise, built-ins override drush 
// commands. Default is help,dd,sa.
// Warning:  bad things can happen if you put the wrong thing here
// (e.g. eval, grep), so be cautious.
// If a drush command overrides a built-in command (e.g. bash help),
// then you can use the `builtin` operator to run the built-in version
// (e.g. `builtin help` to show bash help instead of drush help.)
// If a drush command overrides a shell command (e.g. grep), then
// you can use the regular shell command by typing in the full path
// to the command (e.g. /bin/grep).
# $command_specific['core-cli'] = array('override' => 'help,dd,sa');

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
