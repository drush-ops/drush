# Filtering Drupal Configuration

When exporting and importing configuration from and to a Drupal 8 site,
Drush provides a mechanism called the Configuration Filter system which 
allows configuration values to be altered during import and export, allowing 
you to vary your configuration by environment.  The --skip-modules option
in the config-import and config-export commands is implemented with a
configuration filter.  For more complex uses, you will need to write some
custom code.

## Other Alternatives

The Drupal Configuration system provides the capability to [add configuration
overrides from modules](https://www.drupal.org/node/1928898).  Configuration
overrides should be provided from a module override when possible.  Implementing
an override via a Drush extension is convenient in situations where you would
like to be able to pass values in to the configuration filter via a Drush
commandline option.

## Filtering Drupal Configuration with Drush

Instructions on writing a Drush extension to filter Drupal configuration follows.

### Getting started

The first thing that you will need to do is set up a Drush extension
to hold your storage filter hook.  See the example
[example sandwich commandfile](../examples/sandwich-drush.inc) for
details; note, however, that it is not necessary for your commandfile
to implement hook_drush_command(), or any other hook besides the storage
filter hook.

You will need a composer.json file as well, in order to define where
your StorageFilter class is defined.  Make sure that Drush and your
custom commandfile are required from the composer.json file of any
Drupal site that you plan on using your filter with.

### Implementing the Storage Filter Hook

When Drush imports or exports configuration, it gives all Drush
extensions a chance to hook this process by way of the hook
hook_drush_storage_filters.  The implementation of this hook,
in the file MYFILTER.drush.inc, would look like this:
```
function MYFILTER_drush_storage_filters() {
  $result = array();
  $my_option = drush_get_option('my-option');
  if (!empty($my_option)) {
    $result[] = new MyConfigurationFilter($my_option);
  }
  return $result;
}
```
With this hook in place, MyConfigurationFilter will become part of
the import / export process.

### Implementing a Storage Filter

It is necessary to implement a class that implements 
[StorageFilter](https://github.com/drush-ops/drush/blob/master/lib/Drush/Config/StorageFilter.php).
Your class only needs to implement the two methods defined there,
filterRead() and filterWrite(), to make whichever alterations to configuration
you need during the export and import operations, respectively.  For
an example class that implements StorageFilter, see the
[CoreExtensionFilter](https://github.com/drush-ops/drush/blob/master/lib/Drush/Config/CoreExtensionFilter.php)
class.
