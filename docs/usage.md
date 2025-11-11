Drush can be run in your shell by typing `drush` from within your project root directory or anywhere within Drupal.

    $ drush [options] <command> [argument1] [argument2]

Use the [help command](commands/help.md) to get a list of available options and commands:

    $ drush help pm:list

For even more documentation, use the [topic command](commands/core_topic.md):

    $ drush topic


Drush needs to be told the domain of your site in order for commands to generate correct links (e.g. user:login). You may set a [DRUSH_OPTIONS_URL environment variable](using-drush-configuration.md#environment-variables) (preferred), or use the --uri option.

    $ drush --uri=http://example.com user:login

Site Aliases
------------

Drush lets you run commands on a remote server. Once defined, aliases can be referenced with the @ nomenclature, i.e.

```bash
# Run pending updates on staging site.
$ drush @staging updatedb
# Synchronize staging files to production
$ drush rsync @staging:%files/ @live:%files
# Synchronize database from production to local, excluding the cache table
$ drush sql:sync --structure-tables-key=custom @live @self
```

See [Site aliases](site-aliases.md) for more information.
