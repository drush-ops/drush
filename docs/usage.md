Usage
-----------

With the [Drush Launcher](https://github.com/drush-ops/drush-launcher), Drush can be run in your shell by typing "drush" from within your project root directory or anywhere within Drupal.

    $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

    $ drush help

For even more documentation, use the 'topic' command:

    $ drush topic

Options
-----------

For multisite installations, use the --uri option to target a particular site.  If
you are outside the Drupal web root, you will need to use the --root, --uri or other
command line options just for Drush to work.

    $ drush --uri=http://example.com pm-enable

If you wish to be able to select your Drupal site implicitly from the
current working directory without using the --uri option, but you need your
base_url to be set correctly, you may force it by setting the uri in
a drush.yml file located in the same directory as your settings.php file.

```
uri: "http://example.com"
```

Site Aliases
------------

Drush lets you run commands on a remote server, or even on a set of remote
servers.  Once defined, aliases can be referenced with the @ nomenclature, i.e.

```bash
# Run pending updates on staging site.
$ drush @staging updatedb
# Synchronize staging files to production
$ drush rsync @staging:%files/ @live:%files
# Synchronize database from production to dev, excluding the cache table
$ drush sql-sync --structure-tables-key=custom @live @dev
```

See [example.site.yml](https://raw.githubusercontent.com/drush-ops/drush/master/examples/example.site.yml) for more information.

