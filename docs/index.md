USAGE
-----------

Drush can be run in your shell by typing "drush" from within any Drupal root directory.

    $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

    $ drush help

For even more documentation, use the 'topic' command:

    $ drush topic

OPTIONS
-----------

For multisite installations, use the -l option to target a particular site.  If
you are outside the Drupal web root, you might need to use the -r, -l or other
command line options just for Drush to work. If you do not specify a URI with
-l and Drush falls back to the default site configuration, Drupal's
$GLOBALS['base_url'] will be set to http://default.  This may cause some
functionality to not work as expected.

    $ drush -l http://example.com pm-update

If you wish to be able to select your Drupal site implicitly from the
current working directory without using the -l option, but you need your
base_url to be set correctly, you may force it by setting the uri in
a drushrc.php file located in the same directory as your settings.php file.

**sites/default/drushrc.php:**
```
$options['uri'] = "http://example.com";
```

Related Options:
  ```
  -r <path>, --root=<path>      Drupal root directory to use
                                (defaults to current directory or anywhere in a
                                Drupal directory tree)
  -l <uri> , --uri=<uri>        URI of the Drupal site to use
  -v, --verbose                 Display verbose output.
  ```

COMMANDS
--------

Drush can be extended to run your own commands. Writing a Drush command is no harder than writing simple Drupal modules, since they both follow the same structure.

See [sandwich.drush.inc](https://github.com/drush-ops/drush/blob/master/examples/sandwich.drush.inc) for a quick tutorial on Drush command files.  Otherwise, the core commands in Drush are good models for your own commands.

You can put your Drush command file in a number of places:

  1. In a folder specified with the --include option (see `drush topic docs-configuration`).
  1. Along with one of your enabled modules. If your command is related to an
     existing module, this is the preferred approach.
  1. In a .drush folder in your HOME folder. Note, that you have to create the
     .drush folder yourself.
  1. In the system-wide Drush commands folder, e.g. /usr/share/drush/commands.
  1. In Drupal's /drush or sites/all/drush folders. Note, that you have to create the
     drush folder yourself.

In any case, it is important that you end the filename with .drush.inc, so that Drush can find it.
