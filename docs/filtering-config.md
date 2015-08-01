# Filtering Drupal Configuration

When exporting and importing configuration from and to a Drupal 8 site,
Drush provides a mechanism by which configuration values can be
changed on the fly, allowing you to vary your configuration by
environment.

### Implementing the Hook

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

It is necessary to implement a class that implements StorageFilter.


