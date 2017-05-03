<?php

/**
 * @file
 * Examples of valid statements for a Drush runtime config (drushrc) file.
 *
 * Use this file to cut down on typing out lengthy and repetitive command line
 * options in the Drush commands you use and to avoid mistakes.
 *
 * Rename this file to drushrc.php and optionally copy it to one of the places
 * listed below in order of precedence:
 *
 * 1.  Drupal site folder (e.g. sites/{default|example.com}/drushrc.php).
 * 2.  Drupal /drush and sites/all/drush folders, or the /drush folder
 *       in the directory above the Drupal root.
 * 3.  In any location, as specified by the --config (-c) option.
 * 4.  User's .drush folder (i.e. ~/.drush/drushrc.php).
 * 5.  System wide configuration folder (e.g. /etc/drush/drushrc.php).
 *
 * If a configuration file is found in any of the above locations, it will be
 * loaded and merged with other configuration files in the search list.
 *
 * If you have some configuration options that are specific to a particular
 * version of Drush, then you may place them in a file called drush5rc.php.
 * The version-specific file is loaded in addition to, and after, the general-
 * purpose drushrc file.  Version-specific configuration files can be placed
 * in any of the locations specified above.
 *
 * IMPORTANT NOTE regarding configuration file on Windows:
 *
 * For Windows 7, Windows Vista, Windows Server 2008 and later versions is the
 * system window configuration folder C:\ProgramData\Drush.  For previous
 * versions of Windows is the folder C:\Documents and Settings\All Users\Drush.
 *
 * IMPORTANT NOTE on configuration file loading:
 *
 * At its core, Drush works by "bootstrapping" the Drupal environment in very
 * much the same way that is done during a normal page request from the web
 * server, so most Drush commands run in the context of a fully-initialized
 * website.
 *
 * Configuration files are loaded in the reverse order they are shown above. All
 * configuration files are loaded in the first bootstrapping phase, but
 * a configuration file in a specific Drupal site folder other than the default
 * (eg, sites/example.com/drushrc.php) will not be loaded unless a specific
 * Drupal site is selected.  However, it _will_ be loaded if a site is selected
 * (either via the current working directory or by use of the --uri option),
 * even if the Drush command being run does not bootstrap to the Drupal Site
 * phase.
 *
 * The Drush commands 'rsync' and 'sql-sync' are special cases.  These commands
 * will load the configuration file for the site specified by the source
 * parameter; however, they do not load the configuration file for the site
 * specified by the destination parameter, nor do they load configuration files
 * for remote sites.
 *
 * See `drush topic docs-bootstrap` for more information on how bootstrapping
 * affects the loading of Drush configuration files.
 */

// Specify the base_url that should be used when generating links
# $options['l'] = 'http://example.com/subdir';

// Specify your Drupal core base directory (useful if you use symlinks).
# $options['r'] = '/home/USER/workspace/drupal-6';

/**
 * Useful shell aliases:
 *
 * Drush shell aliases act similar to git aliases.  For best results, define
 * aliases in one of the drushrc file locations between #3 through #6 above.
 * More information on shell aliases can be found via:
 * `drush topic docs-shell-aliases` on the command line.
 *
 * @see https://git.wiki.kernel.org/index.php/Aliases#Advanced
 */
# $options['shell-aliases']['pull'] = '!git pull'; // We've all done it.
# $options['shell-aliases']['pulldb'] = '!git pull && drush updatedb';
# $options['shell-aliases']['cpull'] = 'config-pull @example.prod @self --label=vcs';
# $options['shell-aliases']['noncore'] = 'pm-list --no-core';
# $options['shell-aliases']['wipe'] = 'cache-rebuild';
# $options['shell-aliases']['offline'] = 'state-set system.maintenance_mode 1 --input-format=integer';
# $options['shell-aliases']['online'] = 'state-set system.maintenance_mode 0 --input-format=integer';
# $options['shell-aliases']['self-alias'] = 'site-alias @self --with-db --alias-name=new';
# $options['shell-aliases']['site-get'] = '@none php-eval "return drush_sitealias_site_get();"';
// Save a sanitized sql dump. Customize alias names and --result-file.
# $options['shell-aliases']['sql-dump-sanitized'] = '!drush sql-sync @source @temp --sanitize && drush @temp sql-dump --result-file=/example && drush @temp sql-drop';

// Load a drushrc.php configuration file from the current working directory.
# $options['config'][] = './drushrc.php';

/**
 * Specify folders to search for Drush command files (*.drush.inc).  These
 * values are always merged with include paths defined on the command line or
 * in other configuration files.  On the command line, paths may be separated
 * by a colon (:) on Unix-based systems or a semi-colon (;) on Windows.
 */
# $options['include'] = array('/path/to/commands','/path2/to/more/commands');

/**
 * Specify the modules to ignore when searching for command files (*.drush.inc)
 * inside a Drupal site.
 */
# $options['ignored-modules'] = array('module1', 'module2');

/**
 * Specify the folders to search for Drush alias files (*.alias.drushrc.php and
 * *.aliases.drushrc.php).  These values are always merged with alias paths
 *  defined on the command line or in other configuration files.  On the command
 * line, paths may be separated by a colon (:) on Unix-based systems or a
 * semi-colon (;) on Windows.
 */
# $options['alias-path'] = array('/path/to/aliases','/path2/to/more/aliases');

/**
 * Specify the filename and path where 'sql-dump' should store backups of
 * database dumps.  The default is to dump to STDOUT, however if this option is
 * set in a drushrc.php file, the default behaviour can be achieved by
 * specifying a value of FALSE ("--result-file=0" on the command line).  Two
 * substitution tokens are available: @DATABASE is replaced with the name of the
 * database being dumped, and @DATE is replaced with the current time and date
 * of the dump of the form: YYYYMMDD_HHMMSS.  A value of TRUE ("--result-file=1"
 * on the command line) will cause 'sql-dump' to generate a timestamped destination
 * directory.
 */
# $options['result-file'] = TRUE;
# $options['result-file'] = '/path/to/backup/dir/@DATABASE_@DATE.sql';

// Notify user via Notification Center (OSX) or libnotify (Linux) when command
// takes more than 30 seconds. See global options for more configuration.
# $options['notify'] = 30;

// Enable verbose mode.
# $options['v'] = 1;

/**
 * Specify the logging level for PHP notices.  Defaults to "notice".  Set to
 * "warning" when doing Drush development.  Also make sure that error_reporting
 * is set to E_ALL in your php configuration file.  See `drush status` for the
 * path to your php.ini file.
 */
# $options['php-notices'] = 'warning';

/**
 * Specify the error handling of recoverable errors (E_RECOVERABLE_ERROR).
 * Defaults to 1 and will stop execution of Drush.
 * When set to 0, execution will continue.
 */
# $options['halt-on-error'] = 0;

/**
 * Specify options to pass to ssh in backend invoke.  The default is to prohibit
 * password authentication, and is included here, so you may add additional
 * parameters without losing the default configuration.
 */
# $options['ssh-options'] = '-o PasswordAuthentication=no';

// Set 'remote-os' to 'Windows' to make Drush use Windows shell escape rules
// for remote sites that do not have an 'os' item set.
# $options['remote-os'] = 'Linux';

// By default, unknown options are disallowed and result in an error.  Change
// them to issue only a warning and let command proceed.
# $options['strict'] = FALSE;

/**
 * The output charset suitable to pass to the iconv PHP function's out_charset
 * parameter.
 *
 * Drush will convert its output from UTF-8 to the charset specified here.  It
 * is possible to use //TRANSLIT and //IGNORE charset name suffixes (see iconv
 * documentation).  If not defined, conversion will not be performed.
 */
# $options['output_charset'] = 'ISO-8859-1';
# $options['output_charset'] = 'KOI8-R//IGNORE';
# $options['output_charset'] = 'ISO-8859-1//TRANSLIT';

/**
 * Multiple-site execution options:
 *
 * Some drush commands such as 'sql-sync' are intended for or capable of being
 * executed on multiple sites or server environments and will pass along the
 * options specified here to all instances of the command being executed.
 */

/**
 * By default, Drush will prepend the name of the site to the output of any
 * multiple-site command execution.  To disable this behavior, set the
 * "--no-label" option.
 */
# $options['no-label'] = TRUE;

/**
 * An explicit list of tables which should be included in sql-dump and sql-sync.
 */
# $options['tables']['common'] = array('user', 'permissions', 'role_permission', 'role');

/**
 * List of tables whose *data* is skipped by the 'sql-dump' and 'sql-sync'
 * commands when the "--structure-tables-key=common" option is provided.
 * You may add specific tables to the existing array or add a new element.
 */
# $options['structure-tables']['common'] = array('cache', 'cache_*', 'history', 'search_*', 'sessions', 'watchdog');

/**
 * List of tables to be omitted entirely from SQL dumps made by the 'sql-dump'
 * and 'sql-sync' commands when the "--skip-tables-key=common" option is
 * provided on the command line.  This is useful if your database contains
 * non-Drupal tables used by some other application or during a migration for
 * example.  You may add new tables to the existing array or add a new element.
 */
# $options['skip-tables']['common'] = array('migration_*');

/**
 * Command-specific execution options:
 *
 * Most execution options can be shared between multiple Drush commands; these
 * are specified as top-level elements of the $options array in the prior
 * examples above.  On the other hand, other options are command-specific, and,
 * in some cases, a shared option needs a different configuration depending on
 * which command is being executing.
 *
 * To define options that are only applicable to certain commands, make an entry
 * in the $command-specific array as shown below.  The name of the command may
 * be either the command's full name or any of the command's aliases.
 *
 * Options defined here will be overridden by options of the same name on the
 * command line.  Unary flags such as "--verbose" are overridden via special
 * "--no-xxx" options (e.g. "--no-verbose").
 *
 * Limitation: If 'verbose' is set in a command-specific option, it must be
 * cleared by '--no-verbose', not '--no-v', and visa-versa.
 */

// Ensure all rsync commands use verbose output.
# $command_specific['rsync'] = array('verbose' => TRUE);

// Prevent drush ssh command from adding a cd to Drupal root before provided command.
# $command_specific['ssh'] = array('cd' => FALSE);

// Additional folders to search for scripts.
// Separate by : (Unix-based systems) or ; (Windows).
# $command_specific['script']['script-path'] = 'sites/all/scripts:profiles/myprofile/scripts';

// Set a predetermined username and password when using site-install.
# $command_specific['site-install'] = array('account-name' => 'alice', 'account-pass' => 'secret');

// Use Drupal version specific CLI history instead of per site.
# $command_specific['core-cli'] = array('version-history' => TRUE);
