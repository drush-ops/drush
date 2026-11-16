The System Under Test (https://en.wikipedia.org/wiki/System_under_test): the Drupal codebase that `composer install` places here, for local manual testing and for the kernel test suite.

- `modules/unish` holds the fixture modules that the kernel tests enable.
- `index.php`, `autoload.php` and `autoload_runtime.php` stand in for the files core's scaffold plugin would generate, since that plugin is disabled for this repository.
- `sites/sites.php` serves the `sites/dev` site at the DDEV URL.
