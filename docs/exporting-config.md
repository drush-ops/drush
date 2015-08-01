# Exporting and Importing Configuration

Drush provides commands to export and import the configuration files
to and from a Drupal 8 site.  Configuration can be altered by different
methods in order to provide different behaviors in different environments;
for example, a development server might be configured slightly differently
than the production server.

## Simple Value Changes

It is not necessary to alter the configuration system values to 
make simple value changes to configuration variables, as this may be
done by the configuration override system.

The configuration override system allows you to change configuration
values for a given instance of a site (e.g. the development server) by
setting configuration variables in the site's settings.php file.
For example, to change the name of a local development site:
```
$config['system.site']['name'] = 'Local Install of Awesome Widgets, Inc.';
```

## Ignoring Development Modules

If you have a certain list of modules that should only be enabled on
the deveopment or staging server, then this may be done with the
built-in `--skip-modules` option in the config-export and config-import
commands.

For example, if you want to enable the 'devel' module on development
systems, but not on production server, you could define the following
configuration settings in your drushrc.php file:
```
# $command_specific['config-export']['skip-modules'] = array('devel');
# $command_specific['config-import']['skip-modules'] = array('devel');
```
You may then use `drush pm-enable` to enable the devel module on the
development machine, and subsequent imports of the configuration data
will not cause it to be disabled again.

## More Complex Adjustments

Drush allows more complex changes to the configuration data to be made
via the configuration filter mechanism.  See [filtering configuration](filtering-config.md)
for more information.
