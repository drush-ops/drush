# Exporting and Importing Configuration

Drush provides commands to [export](commands/config_export.md), [pull](commands/config_pull.md), and [import](commands/config_import.md) Drupal configuration files.

## Simple - value changes

It is not necessary to alter configuration values to 
make simple value changes to configuration variables, as this may be
done by the [configuration override system](https://www.drupal.org/node/1928898).

The configuration override system allows you to change configuration
values for a given instance of a site (e.g. the development server) by
setting configuration variables in the site's settings.php file.
For example, to change the name of a local development site:
```php
$config['system.site']['name'] = 'Local Install of Awesome Widgets, Inc.';
```
Note that the configuration override system is a Drupal feature, not
a Drush feature. It should be the preferred method for changing
configuration values on a per-environment basis; however, it does not
work for some things, such as enabling and disabling modules.

## Advanced - variation by environment

- Drupal supports [excluding development modules from enabling on higher environments](https://www.drupal.org/node/3079028).
- See [Config Split](https://www.drupal.org/project/config_split) module for more advanced needs.
