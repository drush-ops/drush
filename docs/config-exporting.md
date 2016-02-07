# Exporting and Importing Configuration

Drush provides commands to export, transfer, and import configuration files
to and from a Drupal 8 site.  Configuration can be altered by different
methods in order to provide different behaviors in different environments;
for example, a development server might be configured slightly differently
than the production server.

This document describes how to make simple value changes to configuration
based on the environment, how to have a different set of enabled modules
in different configurations without affecting your exported configuration
values, and how to make more complex changes.

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
If you wish to change configuration values in code rather than in
your settings.php file, it is also possible to alter configuration
values in module hooks.   See the [configuration override system](https://www.drupal.org/node/1928898)
documentation for details.

Note that the configuration override system is a Drupal feature, not
a Drush feature. It should be the preferred method for changing
configuration values on a per-environment basis; however, it does not
work for some things, such as enabling and disabling modules.  For
configuration changes not handled by the configuration override system,
you can use Drush configuration filters.

## Ignoring Development Modules

If you have a certain list of modules that should only be enabled on
the development or staging server, then this may be done with the
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
will not cause it to be disabled again.  Similarly, if you make changes
to configuration on the development environment and export them, then
the devel module will not be listed in the exports.

