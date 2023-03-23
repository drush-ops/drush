# Site aliases

### Basic usage
In its most basic form, the Drush site alias feature provides a way
for teams to share short names that refer to the live and staging sites
(usually remote) for a given Drupal site.

Add an alias file called `$PROJECT/drush/sites/self.site.yml`,
where `$PROJECT` is the project root (location of composer.json file). The site alias file should be named `self.site.yml` because this name is special, and is used to define the different environments (usually remote)
of the current Drupal site.

The contents of the alias file should look something like the example below:

```yml
# File: self.site.yml
live:
  host: server.domain.com
  user: www-admin
  root: /other/path/to/live/drupal
  uri: http://example.com
stage:
  host: server.domain.com
  user: www-admin
  root: /other/path/to/stage/drupal
  uri: http://stage.example.com
```

The top-level element names (`live` and `stage` in the example above) are
used to identify the different environments available for this site. These
may be used on the command line to select a different target environment
to operate on by prepending an `@` character, e.g. `@live` or `@stage`.

Following these steps, a cache:rebuild on the live environment would be:
```bash
  $ drush @live cache:rebuild
```

All of the available aliases for a site's environments may be listed via:
```bash
  $ drush site:alias @self
```

The elements of a site alias are:

- **host**: The fully-qualified domain name of the remote system
  hosting the Drupal instance. The `host` option
  must be omitted for local sites, as this option controls various
  operations, such as whether or not rsync parameters are for local or
  remote machines, and so on.
- **user**: The username to log in as when using ssh or docker. If each user
   has to use own username, you can create an environment variable which holds
   the value, and reference via ${env.PROJECT_SSH_USER} (for example). Or you may
   omit the `user` item and specify a user in the `~/.ssh/config` file.
- **root**: The Drupal root; must not be specified as a relative path.
- **uri**: The value of --uri should always be the same as
  when the site is being accessed from a web browser (e.g. http://example.com)

Drush typically uses ssh to run commands on remote systems; all team members should
install ssh keys on the target servers (e.g. via `ssh-add`).

### Advanced usage
It is also possible to create site alias files that reference other
sites on the same local system. Site alias files for other local sites
are usually stored in the directory `~/.drush/sites`; however, Drush does
not search this location for alias files by default. To use this location,
you must add the path in your [Drush configuration file](using-drush-configuration.md). For example,
to re-add both of the default user alias path from Drush 8, put the following
in your `~/.drush/drush.yml` configuration file:

```yml
drush:
  paths:
    alias-path:
      - '${env.HOME}/.drush/sites'
      - /etc/drush/sites
```

A canonical alias named _example_ that points to a local
Drupal site named at http://example.com like this:

```yml
# File: example.site.yml
dev:
  root: /path/to/drupal
  uri: http://example.com
```

Note that the first part of the filename (in this case _example_
defines the name of the site alias, and the top-level key _dev_
defines the name of the environment.

With these definitions in place, it is possible to run commands targeting
the dev environment of the target site via:
```bash
  $ drush @example.dev status
```
This command is equivalent to the longer form:
```bash
  $ drush --root=/path/to/drupal --uri=http://example.com status
```
See [Additional Site Alias Options](#additional-site-alias-options) for more information.

### Altering aliases:

See [examples/Commands/SiteAliasAlterCommands.php](https://www.drush.org/latest/examples/SiteAliasAlterCommands.php/)) for an example.

### Site specifications:

When a site alias name is provided on the command line, a site specification may be used instead. A site specification is a site alias that is not saved on the filesystem but instead is provided directly e.g. `drush user@server/path/to/drupal#uri core:status`. See [example site specifications](https://github.com/consolidation/site-alias/blob/ef2eb7d37e59b3d837b4556d4d8070cb345b378c/src/SiteSpecParser.php#L24-L31).

### Environment variables

Site aliases may reference environment variables, just like any Drush config
file. For example, `${env.PROJECT_SSH_USER}` will be replaced by the value
of the `PROJECT_SSH_USER` environment value.

SSH site aliases may set environment variables via the `env-vars` key.
See below.

### Additional Site Alias Options

Aliases are commonly used to define short names for
local or remote Drupal installations; however, an alias
is really nothing more than a collection of options.

- **docker**: When specified, Drush executes via `docker-compose` exec rather than `ssh`.
  - **service**: the name of the container to run on.
  - **exec**:
    - **options**: Options for the exec subcommand.
- **os**: The operating system of the remote server.  Valid values
  are _Windows_ and _Linux_. Set this value for all remote
  aliases where the remote's OS differs from the local. This is especially relevant
  for the [sql:sync](commands/sql_sync.md) command.
- **ssh**: Contains settings used to control how ssh commands are generated
  when running remote commands.
  - **options**: Contains additional commandline options for the `ssh` command
  itself, e.g. `-p 100`
  - **tty**: Usually, Drush will decide whether or not to create a tty (via
  the `ssh --t` option) based on whether the local Drush command is running
  interactively or not. To force Drush to always or never create a tty,
  set the `ssh.tty` option to _true_ or _false_, respectively.
- **paths**: An array of aliases for common rsync targets.
  Relative aliases are always taken from the Drupal root.
  - **files**: Path to _files_ directory.  This will be looked up if not
    specified.
  - **drush-script**: Path to the remote Drush command.
- **command**: These options will only be set if the alias
  is used with the specified command.  In the advanced example below, the option
  `--no-dump` will be selected whenever the `@stage` alias
  is used in any of the following ways:
    - `drush @stage sql-sync @self @live`
    - `drush sql-sync @stage @live`
    - `drush sql-sync @live @stage`
- **env-vars**: An array of key / value pairs that will be set as environment
  variables.

Complex example:

```yml
# File: remote.site.yml
live:
  host: server.domain.com
  user: www-admin
  root: /other/path/to/drupal
  uri: http://example.com
  ssh:
    options: '-p 100'
  paths:
    drush-script: '/path/to/drush'
  env-vars:
    PATH: /bin:/usr/bin:/home/www-admin/.composer/vendor/bin
    DRUPAL_ENV: live
  command:
    site:
      install:
        options:
          admin-password: 'secret-secret'
```

### Site Alias Files for Service Providers

There are a number of service providers that manage Drupal sites as a
service. Drush allows service providers to create collections of site alias
files to reference all of the sites available to a single user. In order
to do this, a new location must be defined in your Drush configuration
file:

```yml
drush:
  paths:
    alias-path:
      - '${env.HOME}/.drush/sites/provider-name'
```

Site aliases stored in this directory may then be referenced by its
full alias name, including its location, e.g.:
```bash
  $ drush @provider-name.example.dev
```
Such alias files may still be referenced by their shorter name, e.g.
`@example.dev`. Note that it is necessary to individually list every
location where site alias files may be stored; Drush never does recursive
(deep) directory searches for alias files.

The `site:alias` command may also be used to list all of the sites and
environments in a given location, e.g.:
```bash
  $ drush site:alias @provider-name
```
Add the option `--format=list` to show only the names of each site and
environment without also showing the values in each alias record.

### Wildcard Aliases for Service Providers

Some service providers that manage Drupal sites allow customers to create
multiple "environments" for a site. It is common for these providers to
also have a feature to automatically create Drush aliases for all of a
user's sites. Rather than write one record for every environment in that
site, it is also possible to write a single _wildcard_ alias that represents
all possible environments. This is possible if the contents of each
environment alias are identical save for the name of the environment in
one or more values. The variable `${env-name}` will be substituted with the
environment name wherever it appears.

Example wildcard record:

```yml
# File: remote-example.site.yml
'*':
  host: ${env-name}.server.domain.com
  user: www-admin
  root: /path/to/${env-name}
  uri: http://${env-name}.remote-example.com
```

With a wildcard record, any environment name may be used, and will always
match. This is not desirable in instances where the specified environment
does not exist (e.g. if the user made a typo). An alias alter hook in a
policy file may be used to catch these mistakes and report an error.
See [SiteAliasAlterCommands](https://www.drush.org/latest/examples/SiteAliasAlterCommands.php/) for an example on how to do this.

### Docker Compose and other transports

The example below shows drush calling into a Docker hosted site. See the https://github.com/consolidation/site-alias and https://github.com/consolidation/site-process projects for more developer
information about transports. 

An example appears below. Edit to suit:

```yml
# File: mysite.site.yml
local:
This environment is an example of the DockerCompose transport.
  docker:
    service: drupal
    exec:
      options: --user USER
stage:
  uri: http://stage.example.com
  root: /path/to/remote/drupal/root
  host: mystagingserver.myisp.com
  user: publisher
  os: Linux
  paths:
   - files: sites/mydrupalsite.com/files
   - custom: /my/custom/path
  command:
    sql:
      sync:
        options:
          no-dump: true
dev:
  root: /path/to/docroot
  uri: https://dev.example.com
```

### Example of rsync with exclude-paths

Note that most options typically passed to rsync via `drush rsync` are
"passthrough options", which is to say they appear after the `--` separator
on the command line. Passthrough options are actually arguments, and
it is not possible to set default arguments in an alias record. The
`drush rsync` command does support two options, `--mode` and `--exclude-paths`,
which are interpreted directly by Drush. Default values for these options
may be specified in an alias record, as shown below.

```yml
dev:
  root: /path/to/docroot
  uri: https://dev.example.com
  command:
    core:
      rsync:
        options:
          mode: rlptz
          exclude-paths: 'css:imagecache:ctools:js:tmp:php:styles'
```

