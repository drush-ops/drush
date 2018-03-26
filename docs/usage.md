Usage
-----------

Drush can be run in your shell by typing "drush" from within your project root directory or anywhere within Drupal.

    $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

    $ drush help

For even more documentation, use the 'topic' command:

    $ drush topic

Using the --uri option and --root options.
-----------

For multisite installations, use a site alias or the --uri option to target a particular site.

    $ drush --uri=http://example.com pm:enable
    
If you are outside the Composer project and not using a site alias, you need to specify --root and --uri for Drush to locate and bootstrap the right Drupal site.

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

See [example.site.yml](https://raw.githubusercontent.com/drush-ops/drush/master/examples/example.site.yml) for more information.

