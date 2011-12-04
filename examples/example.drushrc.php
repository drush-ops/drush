<?php

/**
 * Examples of valid statements for a drushrc.php file. Use this file to
 * cut down on typing of options and avoid mistakes.
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
 * If you have some configuration options that are specific to a
 * particular version of drush, then you may place them in a file
 * called drush5rc.php.  The version-specific file is loaded in
 * addtion to, and after, the general-purpose drushrc.php file.
 * Version-specific configuration files can be placed in any of the
 * locations specified above.
 *
 * IMPORTANT NOTE on configuration file loading:
 *
 * At its core, drush works by "bootstrapping" the Drupal environment
 * in very much the same way that is done during a normal page request
 * from the web server, so most drush commands run in the context
 * of a fully-initialized website.
 *
 * Configuration files are loaded in the reverse order they are
 * shown above.  All configuration files are loaded in the first
 * bootstrapping phase, but the configuration files stored at
 * a Drupal root or Drupal site folder will not be loaded in
 * instances where no Drupal site is selected.  However, they
 * _will_ still be loaded if a site is selected (either via
 * the current working directory or by use of the --root and
 * --uri options), even if the drush command being run does
 * not bootstrap to the Drupal Root or Drupal Site phase.
 * Note that this is different than Drush-4.x and earlier, which
 * did not load these configuration files until the Drupal site
 * was bootstrapped.
 *
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

// Drush shell aliases act similar to git aliases.
// See https://git.wiki.kernel.org/index.php/Aliases#Advanced.
// For best success, define these in drushrc files located in #6-3 above.
// More information on shell aliases can be found in
// `drush topic docs-shell-aliases`
# $options['shell-aliases']['pull'] = '!git pull'; // We've all done it.
# $options['shell-aliases']['pulldb'] = '!git pull && drush updatedb';
# $options['shell-aliases']['noncore'] = 'pm-list --no-core';
# $options['shell-aliases']['wipe'] = 'cache-clear all';
# $options['shell-aliases']['unsuck'] = 'pm-disable -y overlay,dashboard';
# $options['shell-aliases']['offline'] = 'variable-set -y --always-set maintenance_mode 1';
# $options['shell-aliases']['online'] = 'variable-delete -y --exact maintenance_mode';
// Add a 'pm-clone' to simplify (cached) git cloning from drupal.org.
# $options['shell-aliases']['pm-clone'] = 'pm-download --gitusername=YOURUSERNAME --package-handler=git_drupalorg --cache';

// Load a drushrc.php configuration file from the current working directory.
# $options['c'] = '.';

// Control automatically check for updates in pm-updatecode and drush version.
// FALSE = never check for updates.  'head' = allow updates to drush-HEAD.
// TRUE (default) = allow updates to latest stable release.
# $options['self-update'] = FALSE;

// By default, drush will download projects compatibile with the
// current version of Drupal, or, if no Drupal site is specified,
// then the Drupal-7 version of the project is downloaded.  Set
// default-major to select a different default version.
# $options['default-major'] = 6;

// Specify Git clones for drupal.org extensions.
# $options['package-handler'] = 'git_drupalorg';

// Specify additional directories to search for *.drush.inc files.
// Always merged with include paths defined on the commandline or in
// other configuration files.  On the command line, paths can be separated
// by : (Unix-based systems) or ; (Windows).
# $options['include'] = array('/path/to/commands','/path2/to/more/commands');

// Specify modules to ignore when searching for *.drush.inc files
// inside a Drupal site
# $options['ignored-modules'] = array('module1', 'module2');

// Specify directories to search for *.alias.drushrc.php and
// *.aliases.drushrc.php files.  Always merged with alias paths
// defined on the commandline or in other configuration files.
// On the command line, paths can be separated by : (Unix-based systems) or ; (Windows).
# $options['alias-path'] = array('/path/to/aliases','/path2/to/more/aliases');

// Specify directory where sql-sync will store persistent dump files.
// Keeping the dump files around will improve the performance of rsync
// when the database is rsync'ed to a remote system.  If a dump directory
// is not specified, then sql-sync will store dumps in temporary files.
# $options['dump-dir'] = '/path/to/dumpdir';

// Specify directory where sql-dump should store backups of database
// dumps.  @DATABASE is replaced with the name of the database being
// dumped, and @DATE is replaced with the current time and date of the
// dump.
// If set, this can be explicitly overridden by specifying --result-file
// on the commandline.  The default behavior of dumping to
// STDOUT can be achieved via --result-file=0
# $options['result-file'] = '/path/to/backup/dir/@DATABASE_@DATE.sql';
// TRUE will cause sql-dump to use the same backup directory that pm-updatecode
// does.
# $options['result-file'] = TRUE;

// Enable verbose mode.
# $options['v'] = 1;

// Show database passwords in 'status' and 'sql-conf' commands
# $options['show-passwords'] = 1;

// Default logging level for PHP notices. Defaults to "notice".
// Set to "warning" when doing drush development. Also make sure that
// error_reporting is set to E_ALL in your php configuration file.
// See 'drush status' for the path to your php.ini file.
# $options['php-notices'] = 'warning';

// Specify options to pass to ssh in backend invoke.
// Default is to prohibit password authentication.
# $options['ssh-options'] = '-o PasswordAuthentication=no';

// Set 'remote-os' to 'Windows' to make drush use Windows shell escape
// rules for remote sites that do not have an 'os' item set.
# $options['remote-os'] = 'Linux';

// By default, unknown options are disallowed and result in an error.
// Change them to issue only a warning and let command proceed.
# $options['strict'] = FALSE;

// rsync version 2.6.8 or earlier will give an error message:
// "--remove-source-files: unknown option".  To fix this, set
// $options['rsync-version'] = '2.6.8'; (replace with the lowest
// version of rsync installed on any system you are using with
// drush).  Note that drush requires at least rsync version 2.6.4
// for some functions to work correctly.
// Note that this option can also be set in a site alias.  This
// is preferable if newer versions of rsync are available on some
// of the systems you use.
// See: http://drupal.org/node/955092
# $options['rsync-version'] = '2.6.9';

/**
 * The output charset suitable to pass to iconv PHP function as out_charset parameter.
 *
 * Drush will convert its output from UTF-8 to the charset specified here. It is
 * possible to use //TRANSLIT and //IGNORE charset name suffixes (see iconv
 * documentation). If not defined conversion will not be performed.
 */
# $options['output_charset'] = 'ISO-8859-1';
# $options['output_charset'] = 'KOI8-R//IGNORE';
# $options['output_charset'] = 'ISO-8859-1//TRANSLIT';

/**
 * Multiple command execution options.
 *
 * By default, drush will prepend the name of the site to the output of any
 * multiple-site command execution. To disable this behavior, set the --no-label
 * option.
 */
# $options['no-label'] = TRUE;

/**
 * Customize this associative array with your own tables. This is the list of
 * tables whose *data* is skipped by the 'sql-dump' and 'sql-sync' commands when
 * a structure-tables-key is provided. You may add new tables to the existing
 * array or add a new element.
 */
# $options['structure-tables']['common'] = array('cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'sessions', 'watchdog');

/**
 * Customize this associative array with your own tables. This is the list of
 * tables that are entirely omitted by the 'sql-dump' and 'sql-sync' commands
 * when a skip-tables-key is provided. This is useful if your database contains
 * non Drupal tables used by some other application or during a migration for
 * example. You may add new tables to the existing array or add a new element.
 */
# $options['skip-tables']['common'] = array('migration_data1', 'migration_data2');

/**
 * Override specific entries in Drupal's 'variable' table or settings.php
 */
# $options['variables']['site_name'] = 'My Drupal site';
# $options['variables']['theme_default'] = 'minnelli';
# $options['variables']['anonymous'] = 'Visitor';

/**
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

// Set a predetermined username and password when using site-install.
# $command_specific['site-install'] = array('account-name' => 'alice', 'account-pass' => 'secret');

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

// Load a drushrc.php file from the 'drush' folder at the root
// of the current git repository. Customize as desired.
// (Script by grayside; @see: http://grayside.org/node/93)
#exec('git rev-parse --git-dir 2> /dev/null', $output);
#if (!empty($output)) {
#  $repo = $output[0];
#  $options['config'] = $repo . '/../drush/drushrc.php';
#  $options['include'] = $repo . '/../drush/commands';
#  $options['alias-path'] = $repo . '/../drush/aliases';
#}
