<?php

/**
 * @file
 * Example of valid statements for an alias file.
 *
 * Use this file as a guide to creating your own aliases.
 *
 * Aliases are commonly used to define short names for
 * local or remote Drupal installations; however, an alias
 * is really nothing more than a collection of options.
 * A canonical alias named "dev" that points to a local
 * Drupal site named "http://example.com" looks like this:
 *
 * @code
 * $aliases['dev'] = array(
 *   'root' => '/path/to/drupal',
 *   'uri' => 'http://example.com',
 * );
 * @endcode
 *
 * With this alias definition, then the following commands
 * are equivalent:
 *
 *   $ drush @dev status
 *   $ drush --root=/path/to/drupal --uri=http://example.com status
 *
 * See the --uri option documentation below for hints on setting its value.
 *
 * Any option that can be placed on the drush commandline
 * can also appear in an alias definition.
 *
 * There are several ways to create alias files.
 *
 *   + Put each alias in a separate file called ALIASNAME.alias.drushrc.php
 *   + Put multiple aliases in a single file called aliases.drushrc.php
 *   + Put groups of aliases into files called GROUPNAME.aliases.drushrc.php
 *
 * Drush will search for aliases in any of these files using
 * the alias search path.  The following locations are examined
 * for alias files:
 *
 *   1. In any path set in $options['alias-path'] in drushrc.php,
 *      or (equivalently) any path passed in via --alias-path=...
 *      on the command line.
 *   2. In one of the default locations:
 *        a. /etc/drush
 *        b. $HOME/.drush
 *   3. In one of the site-specific locations:
 *        a. The /drush and /sites/all/drush folders for the current Drupal site
 *        b. The /drush folder in the directory above the current Drupal site
 *
 * These locations are searched recursively.  If there is a folder called
 * 'site-aliases' in any search path, then Drush will search for site aliases
 * only inside that directory.
 *
 * The preferred locations for alias files, then, are:
 *
 *   /etc/drush/site-aliases
 *   $HOME/.drush/site-aliases
 *   $ROOT/drush/site-aliases
 *   $ROOT/sites/all/drush/site-aliases
 *   $ROOT/../drush/site-aliases
 *
 * Or any path set in $options['alias-path'] or via --alias-path.
 *
 * Folders and files containing other versions of drush in their names will
 * be *skipped* (e.g. mysite.aliases.drush4rc.php or
 * drush4/mysite.aliases.drushrc.php). Names containing the current version of
 * Drush (e.g. mysite.aliases.drush5rc.php) will be loaded.
 *
 * Files stored in these locations can be used to create aliases
 * to local and remote Drupal installations.  These aliases can be
 * used in place of a site specification on the command line, and
 * may also be used in arguments to certain commands such as
 * "drush rsync" and "drush sql-sync".
 *
 * Alias files that are named after the single alias they contain
 * may use the syntax for the canonical alias shown at the top of
 * this file, or they may set values in $options, just
 * like a drushrc.php configuration file:
 *
 * @code
 * $options['uri'] = 'http://example.com';
 * $options['root'] = '/path/to/drupal';
 * @endcode
 *
 * When alias files use this form, then the name of the alias
 * is taken from the first part of the alias filename.
 *
 * Alias groups (aliases stored together in files called
 * GROUPNAME.aliases.drushrc.php, as mentioned above) also
 * create an implicit namespace that is named after the group
 * name.
 *
 * For example:
 *
 * @code
 * # File: mysite.aliases.drushrc.php
 * $aliases['dev'] = array(
 *   'root' => '/path/to/drupal',
 *   'uri' => 'http://example.com',
 * );
 * $aliases['live'] = array(
 *   'root' => '/other/path/to/drupal',
 *   'uri' => 'http://example.com',
 * );
 * @endcode
 *
 * Then the following special aliases are defined:
 * - @mysite: An alias named after the groupname may be used to reference all of
 *   the aliases in the group (e.g., `drush @mydrupalsite status`).
 * - @mysite.dev: A copy of @dev.
 * - @mysite.live: A copy of @live.
 *
 * Thus, aliases defined in an alias group file may be referred to
 * either by their simple (short) name, or by their full namespace-qualified
 * name.
 *
 * To see an example alias definition for the current bootstrapped
 * site, use the "site-alias" command with the built-in alias "@self":
 *
 *   $ drush site-alias @self
 *
 * TIP:  If you would like to have drush include a 'databases' record
 * in the output, include the options --with-db and --show-passwords:
 *
 *   $ drush site-alias @self --with-db --show-passwords
 *
 * Drush also supports *remote* site aliases.  When a site alias is
 * defined for a remote site, Drush will use the ssh command to run
 * the requested command on the remote machine.  The simplest remote
 * alias looks like this:
 *
 * @code
 * $aliases['live'] = array(
 *   'remote-host' => 'server.domain.com',
 *   'remote-user' => 'www-admin',
 * );
 * @endcode
 *
 * The form above requires that Drush be installed on the remote machine,
 * and that there also be an alias of the same name defined on that
 * machine.  The remote alias should define the 'root' and 'uri' elements,
 * as shown in the initial example at the top of this file.
 *
 * If you do not wish to maintain site aliases on the remote machine,
 * then you may define an alias that contains all of the elements
 * 'remote-host', 'remote-user', 'root' and 'uri'.  If you do this, then
 * Drush will make the remote call using the --root and --uri options
 * to identify the site, so no site alias is required on the remote server.
 *
 * @code
 * $aliases['live'] = array(
 *   'remote-host' => 'server.domain.com',
 *   'remote-user' => 'www-admin',
 *   'root' => '/other/path/to/drupal',
 *   'uri' => 'http://example.com',
 * );
 * @endcode
 *
 * If you would like to see all of the Drupal sites at a specified
 * root directory, use the built-in alias "@sites":
 *
 *   $ drush -r /path/to/drupal site-alias @sites
 *
 * It is also possible to define explicit lists of sites using a special
 * alias list definition.  Alias lists contain a list of alias names in
 * the group, and no other information.  For example:
 *
 * @code
 * $aliases['mydevsites'] = array(
 *   'site-list' => array('@mysite.dev', '@otherside.dev')
 * );
 * @endcode
 *
 * The built-in alias "@none" represents the state of no Drupal site;
 * to ignore the site at the cwd and just see default drush status:
 *
 *   $ drush @none status
 *
 * Wildcard Aliases for Service Providers
 *
 * Some service providers that manage Drupal sites allow customers to create
 * multiple "environments" for a site. It is common for these providers to
 * also have a feature to automatically create Drush aliases for all of a
 * user's sites. Rather than write one record for every environment in that
 * site, it is also possible to write a single "wildcard" alias that represents
 * all possible environments. This is possible if the contents of each
 * environment alias are identical save for the name of the environment in
 * one or more values. The variable `${env-name}` will be substituted with the
 * environment name wherever it appears.
 *
 * Example wildcard record:
 *
 * @code
 *   $aliases['remote-example.*'] = array(
 *     'remote-host' => '${env-name}.server.domain.com',
 *     'remote-user' => 'www-admin',
 *     'root' => '/path/to/${env-name}',
 *     'uri' => '${env-name}.example.com',
 *   );
 * @endcode
 *
 * With a wildcard record, any environment name may be used, and will always
 * match. This is not desirable in instances where the specified environment
 * does not exist (e.g. if the user made a typo). An alias alter hook in a
 * policy file may be used to catch these mistakes and report an error.
 * @see policy.drush.inc for an example on how to do this.

 *
 * See `drush help site-alias` for more options for displaying site
 * aliases.  See `drush topic docs-bastion` for instructions on configuring
 * remote access to a Drupal site behind a firewall via a bastion server.
 *
 * Although most aliases will contain only a few options, a number
 * of settings that are commonly used appear below:
 *
 * - 'uri': In Drupal 7 and 8, the value of --uri should always be the same as
 *   when the site is being accessed from a web browser (e.g. http://example.com)
 *   In Drupal 6, the value of --uri should always be the same as the site's folder
 *   name in the 'sites' folder (e.g. default); it is best if the site folder name
 *   matches the URI from the browser, and is consistent on every instance of the
 *   same site (e.g. also use sites/example.com for http://example.com).
 * - 'root': The Drupal root; must not be specified as a relative path.
 * - 'remote-host': The fully-qualified domain name of the remote system
 *   hosting the Drupal instance. **Important Note: The remote-host option
 *   must be omitted for local sites, as this option controls various
 *   operations, such as whether or not rsync parameters are for local or
 *   remote machines, and so on. @see hook_drush_sitealias_alter() in drush.api.php
 * - 'remote-user': The username to log in as when using ssh or rsync.
 * - 'os': The operating system of the remote server.  Valid values
 *   are 'Windows' and 'Linux'. Be sure to set this value for all remote
 *   aliases because the default value is PHP_OS if 'remote-host'
 *   is not set, and 'Linux' (or $options['remote-os']) if it is. Therefore,
 *   if you set a 'remote-host' value, and your remote OS is Windows, if you
 *   do not set the 'OS' value, it will default to 'Linux' and could cause
 *   unintended consequences, particularly when running 'drush sql-sync'.
 * - 'ssh-options': If the target requires special options, such as a non-
 *   standard port, alternative identity file, or alternative
 *   authentication method, ssh-options can contain a string of extra
 *   options that are used with the ssh command, eg "-p 100"
 * - 'parent': Deprecated.  See "altering aliases", below.
 * - 'path-aliases': An array of aliases for common rsync targets.
 *   Relative aliases are always taken from the Drupal root.
 *   - '%drush-script': The path to the 'drush' script, or to 'drush.php'.
 *     This is used by backend invoke when drush
 *     runs a drush command.  The default is 'drush' on remote machines, or
 *     the full path to drush.php on the local machine.
 *   - '%drush': A read-only property: points to the folder that the drush
 *     script is stored in.
 *   - '%files': Path to 'files' directory.  This will be looked up if not
 *     specified.
 *   - '%root': A reference to the Drupal root defined in the 'root' item in the
 *     site alias record.
 * - 'php': path to custom php interpreter. Windows support limited to Cygwin.
 * - 'php-options': commandline options for php interpreter, you may
 *   want to set this to '-d error_reporting="E_ALL^E_DEPRECATED"'
 * - 'variables' : An array of name/value pairs which override Drupal
 *   variables/config. These values take precedence even over settings.php
 *   overrides.
 * - 'command-specific': These options will only be set if the alias
 *   is used with the specified command.  In the example below, the option
 *   `--no-dump` will be selected whenever the @stage alias
 *   is used in any of the following ways:
 *   - `drush @stage sql-sync @self @live`
 *   - `drush sql-sync @stage @live`
 *   - `drush sql-sync @live @stage`
 *   In case of conflicting options, command-specific options in targets
 *   (source and destination) take precedence over command-specific options
 *   in the bootstrapped site, and command-specific options in a destination
 *   alias will take precedence over those in a source alias.
 * - 'source-command-specific' and 'target-command-specific': Behaves exactly
 *   like the 'command-specific' option, but is applied only if the alias
 *   is used as the source or target, respectively, of an rsync or sql-sync
 *   command.  In the example below, `--skip-tables-list=comments` whenever
 *   the alias @live is the target of an sql-sync command, but comments will
 *   be included if @live is the source for the sql-sync command.
 * - '#peer': Settings that begin with a '#' are not used directly by Drush, and
 *   in fact are removed before making a backend invoke call (for example).
 *   These kinds of values are useful in conjunction with shell aliases.  See
 *   `drush topic docs-shell-aliases` for more information on this.
 * - '#env-vars': An associative array of keys and values that should be set on
 *    the remote side before invoking drush.
 * - rsync command options have specific requirements in order to
 *   be passed through by Drush. See the comments on the sample below:
 *
 * @code
 * 'command-specific' => array (
 *   'core-rsync' => array (
 *
 *     // single-letter rsync options are placed in the 'mode' key
 *     // instead of adding '--mode=rultvz' to drush rsync command.
 *     'mode' => 'rultvz',
 *
 *     // multi-letter rsync options without values must be set to
 *     // TRUE or NULL to work (i.e. setting $VALUE to 1, 0, or ''
 *     // will not work).
 *     'delete' => TRUE,
 *
 *     // if you need multiple excludes, use an rsync exclude file
 *     'exclude-from' => "'/etc/rsync/exclude.rules'",
 *
 *     // filter options with white space must be wrapped in "" to preserve
 *     // the inner ''.
 *     'filter' => "'exclude *.sql'",
 *
 *     // if you need multple filter options, see rsync merge-file options
 *     'filter' => "'merge /etc/rsync/default.rules'",
 *   ),
 * ),
 * @endcode
 *
 * Altering aliases:
 *
 * Alias records are written in php, so you may use php code to alter
 * alias records if you wish.  For example:
 *
 * @code
 * $common_live = array(
 *   'remote-host' => 'myserver.isp.com',
 *   'remote-user' => 'www-admin',
 * );
 *
 * $aliases['live'] = array(
 *   'uri' => 'http://example.com',
 *   'root' => '/path.to/root',
 * ) + $common_live;
 * @endcode
 *
 * If you wish, you might want to put $common_live in a separate file,
 * and include it at the top of each alias file that uses it.
 *
 * You may also use a policy file to alter aliases in code as they are
 * loaded by Drush.  See policy_drush_sitealias_alter in
 * `drush topic docs-policy` for details.
 *
 * Some examples appear below.  Remove the leading hash signs to enable.
 */

#$aliases['stage'] = array(
#    'uri' => 'http://stage.example.com',
#    'root' => '/path/to/remote/drupal/root',
#    'remote-host' => 'mystagingserver.myisp.com',
#    'remote-user' => 'publisher',
#    'os' => 'Linux',
#    'path-aliases' => array(
#      '%drush' => '/path/to/drush',
#      '%drush-script' => '/path/to/drush/drush',
#      '%files' => 'sites/mydrupalsite.com/files',
#      '%custom' => '/my/custom/path',
#     ),
#     'variables' => array(
#        'site_name' => 'My Drupal site',
#      ),
#     'command-specific' => array (
#       'sql-sync' => array (
#         'no-dump' => TRUE,
#       ),
#     ),
#     # This shell alias will run `mycommand` when executed via
#     # `drush @stage site-specific-alias`
#     'shell-aliases' => array (
#       'site-specific-alias' => '!mycommand',
#     ),
#  );
#$aliases['dev'] = array(
#    'uri' => 'http://dev.example.com',
#    'root' => '/path/to/drupal/root',
#    'variables' => array(
#      'mail_system' => array('default-system' => 'DevelMailLog'),
#    ),
#  );
#$aliases['server'] = array(
#    'remote-host' => 'mystagingserver.myisp.com',
#    'remote-user' => 'publisher',
#    'os' => 'Linux',
#  );
#$aliases['live'] = array(
#    'uri' => 'http://example.com',
#    'root' => $aliases['dev']['root'],
#  ) + $aliases['server'];
