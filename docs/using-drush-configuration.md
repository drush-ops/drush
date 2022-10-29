Drush Configuration
===================
Drush configuration is useful to cut down on typing out lengthy and repetitive command line
options, and to avoid mistakes.

#### Directories and Discovery
drush.yml files are discovered as below, in order of precedence:

1.  Drupal site folder (e.g. `sites/{example.com}/drush.yml`).
2.  `sites/all/drush`, `WEBROOT/drush`, or `PROJECTROOT/drush`.
3.  In any location, as specified by the `--config` option.
4.  User's .drush folder (i.e. `~/.drush/drush.yml`).
5.  System-wide configuration folder (e.g. `/etc/drush/drush.yml` or `C:\ProgramData\Drush\drush.yml`).

If a configuration file is found in any of the above locations, it will be
loaded and merged with other configuration files in the search list. Run `drush status --fields=drush-conf` 
to see all discovered config files.

#### Environment variables

Your Drush config file may reference environment variables using a syntax like `${env.HOME}`.
For example see the `drush.paths` examples below.

An alternative way to populate Drush configuration is to define environment variables that
correspond to config keys. For example, to populate the `options.uri` config item,
create an environment variable `DRUSH_OPTIONS_URI=http://example.com`.
As you can see, variable names should be uppercased, prefixed with `DRUSH_`, and periods
replaced with dashes.

### Config examples

#### Specify config files to load
```yml
drush:
  paths:
    config:
      # Load any personal config files. Is silently skipped if not found. Filename must be drush.yml
      - ${env.HOME}/.drush/config/drush.yml
```

- The value may be path to a file, or to a directory containing drush.yml file(s).
- View discovered config paths: `drush status --fields=drush-conf --format=yaml`

#### Specify folders to search for Drush command files.
These locations are always merged with include paths defined on the command line or
in other configuration files.  On the command line, paths may be separated
by a colon `:` on Unix-based systems or a semi-colon `;` on Windows,
or multiple `--include` options may be provided. Drush 8 and earlier did
a deep search in `~/.drush` and `/usr/share/drush/commands` when loading
command files, so we mimic that here as an example.

```yml
drush:
  include:
    - '${env.HOME}/.drush/commands'
    - /usr/share/drush/commands
```

- View all loaded commands: `drush list`

#### Specify the folders to search for Drush alias files (*.site.yml). 
These locations are always merged with alias paths defined on the command line
 or in other configuration files.  On the command line, paths may be
 separated by a colon `:` on Unix-based systems or a semi-colon `;` on
 Windows, or multiple `--alias-path` options may be provided. Note that
 Drush 8 and earlier did a deep search in `~/.drush` and `/etc/drush` when
 loading alias files.
```yml 
drush:
  paths:
    alias-path:
      - '${env.HOME}/.drush/sites'
      - /etc/drush/sites
```
- View all loaded site aliases: `drush site:alias`

#### Backup directory
Specify a folder where Drush should store backup files, including
temporary sql dump files created during [sql:sync](https://www.drush.org/latest/commands/sql_sync/). If unspecified,
defaults to `$HOME/drush-backups`.
```yml
drush:
  paths:
    backup-dir: /tmp/drush-backups
```

#### Global options
```yml
options:
  # Specify the base_url that should be used when generating links.
  uri: 'http://example.com/subdir'
  
  # Specify your Drupal core base directory (useful if you use symlinks).
  root: '/home/USER/workspace/drupal'
  
  # Enable verbose mode.
  verbose: true
```

#### Command-specific options
```yml
command:
  sql:
    dump:
      options:
        # Omit cache and similar tables (including during a sql:sync).
          structure-tables-key: common
  php:
    script:
      options:
        # Additional folders to search for scripts.
        script-path: 'sites/all/scripts:profiles/myprofile/scripts'
  core:
    rsync:
      options:
        # Ensure all rsync commands use verbose output.
        verbose: true

  site:
    install:
      options:
        # Set a predetermined username and password when using site:install.
        account-name: 'alice'
        account-pass: 'secret'
```

#### Non-options
```yml
sql:
  # An explicit list of tables which should be included in sql-dump and sql-sync.
  tables:
    common:
      - user
      - permissions
      - role_permissions
      - role
  # List of tables whose *data* is skipped by the 'sql-dump' and 'sql-sync'
  # commands when the "--structure-tables-key=common" option is provided.
  # You may add specific tables to the existing array or add a new element.
  structure-tables:
    common:
      - cache
      - 'cache_*'
      - history
      - 'search_*'
      - 'sessions'
      - 'watchdog'
  # List of tables to be omitted entirely from SQL dumps made by the 'sql-dump'
  # and 'sql-sync' commands when the "--skip-tables-key=common" option is
  # provided on the command line.  This is useful if your database contains
  # non-Drupal tables used by some other application or during a migration for
  # example.  You may add new tables to the existing array or add a new element.
  skip-tables:
    common:
      - 'migration_*'

ssh:
  # Specify options to pass to ssh.  The default is to prohibit
  # password authentication, and is included here, so you may add additional
  # parameters without losing the default configuration.
  options: '-o PasswordAuthentication=no'
  # This string is valid for Bash shell. Override in case you need something different. See https://github.com/drush-ops/drush/issues/3816.
  pipefail: 'set -o pipefail; '

notify:
  # Notify when command takes more than 30 seconds.
  duration: 30
  # Specify a command to run. Defaults to Notification Center (OSX) or libnotify (Linux)
  cmd: /path/to/program
  # See https://github.com/drush-ops/drush/blob/11.x/src/Commands/core/NotifyCommands.php for more settings.

xh:
  # Start profiling via xhprof/tideways and show a link to the run report.
  link: http://xhprof.local
  # See https://github.com/drush-ops/drush/blob/11.x/src/Commands/core/XhprofCommands.php for more settings.
  profile-builtins: true
  profile-cpu: false
  profile-memory: false
```

### Misc
- If you are authoring a commandfile and wish to access the user's configuration, see [Command Authoring](commands.md).
- [Setting boolean options broke with Symfony 3](https://github.com/drush-ops/drush/issues/2956). This will be fixed
  in a future release.  
- Version-specific configuration. Limit the version of Drush that will load a configuration file by placing
the Drush major version number in the filename, e.g. `drush10.yml`.
- The Drush configuration system has been factored out of Drush and shared with the world at [https://github.com/consolidation/config](https://github.com/consolidation/config). Feel free to use it for your projects. Lots more usage information is there.
