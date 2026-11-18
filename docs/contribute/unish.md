Drush's command tests extend Drupal core's `KernelTestBase`. They instantiate a Symfony Console CommandTester object as is typical. See [CircleCi](https://circleci.com/gh/drush-ops/drush).

## Usage
1. `git clone https://github.com/drush-ops/drush.git`
1. `cd drush`
1. `composer install`
1. Set `UNISH_DB_URL` for the kernel suite, for example `sqlite://localhost/:memory:?module=sqlite`.
1. Run all test suites: `composer test`

## DDEV
Drush's own tests may be run within provided Docker containers via [DDEV](https://ddev.readthedocs.io/en/stable/). It sets `UNISH_DB_URL` for you.

- Start containers: `ddev start`
- Run a test: `ddev exec composer kernel -- --filter testUserRole`
- See the [DDEV's XDebug docs](https://ddev.readthedocs.io/en/stable/users/debugging-profiling/step-debugging/) for help on enabling Xdebug.

## Advanced usage
- Run only one test suite
    - `composer unit`
    - `composer kernel`
- Run only tests matching a regex: `composer kernel -- --filter testUserRole`
- XML results: `composer kernel -- --log-junit results.xml`

## About the Test Suites
- **Unit tests** operate on functions that take values and return results without creating side effects. No database connection is required to run these tests, and no Drupal site is set up. They are configured in [tests/phpunit.xml.dist](https://github.com/drush-ops/drush/blob/15.x/tests/phpunit.xml.dist).
- **Kernel tests** extend Drupal core's `KernelTestBase` and drive a command directly with Symfony's `CommandTester`, the way Drupal core tests its own console commands. They run from [tests/phpunit.kernel.xml.dist](https://github.com/drush-ops/drush/blob/15.x/tests/phpunit.kernel.xml.dist), whose bootstrap chains core's test bootstrap and derives `SIMPLETEST_DB` from `UNISH_DB_URL`. Each test gets a fresh, minimal Drupal with only the modules it lists. A class marked `#[ShareEnvironment]` builds that Drupal once, in `setUpEnvironment()`, and its test methods share the database; see the [change record](https://www.drupal.org/node/3623403). [QueueTest](https://github.com/drush-ops/drush/blob/15.x/tests/kernel/QueueTest.php) is a good model, both for Drush's own commands and for commands that ship in your module.
