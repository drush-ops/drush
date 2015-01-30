Usage
-----------

Drush can be run in your shell by typing "drush" from within any Drupal root directory.

    $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

    $ drush help

For even more documentation, use the 'topic' command:

    $ drush topic

Options
-----------

For multisite installations, use the --uri option to target a particular site.  If
you are outside the Drupal web root, you might need to use the --root, --uri or other
command line options just for Drush to work. If you do not specify a URI, Drush falls 
back to the default site configuration, Drupal's $GLOBALS['base_url'] will be set to http://default.  This may cause some
functionality to not work as expected.

    $ drush --uri http://example.com pm-updatecode

If you wish to be able to select your Drupal site implicitly from the
current working directory without using the -l option, but you need your
base_url to be set correctly, you may force it by setting the uri in
a drushrc.php file located in the same directory as your settings.php file. *This is broken - see https://github.com/drush-ops/drush/issues/800*

```
$options['uri'] = "http://example.com";
```

Site Aliases
------------

Drush lets you run commands on a remote server, or even on a set of remote
servers.  Once defined, aliases can be references with the @ nomenclature, i.e.

```bash
# Synchronize staging files to production
$ drush rsync @staging:%files/ @live:%files
# Syncronize database from production to dev, excluding the cache table
$ drush sql-sync --structure-tables-key=custom @live @dev
```

See [example.aliases.drushrc.php](https://raw.githubusercontent.com/drush-ops/drush/master/examples/example.aliases.drushrc.php) for more information.

