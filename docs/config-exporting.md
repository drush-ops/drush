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
done by the [configuration override system](https://www.drupal.org/node/1928898).

The configuration override system allows you to change configuration
values for a given instance of a site (e.g. the development server) by
setting configuration variables in the site's settings.php file.
For example, to change the name of a local development site:
```
$config['system.site']['name'] = 'Local Install of Awesome Widgets, Inc.';
```
Note that the configuration override system is a Drupal feature, not
a Drush feature. It should be the preferred method for changing
configuration values on a per-environment basis; however, it does not
work for some things, such as enabling and disabling modules.  For
configuration changes not handled by the configuration override system,
you can use Drush configuration filters.

## Ignoring Development Modules

Use the [Config Split](https://www.drupal.org/project/config_split) module to
split off development configuration in a dedicated config directory.
