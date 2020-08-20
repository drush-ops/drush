Drush's test suite (aka Unish) is based on [PHPUnit](http://www.phpunit.de). In order to maintain
high quality, our tests are run on every push. See [CircleCi](https://circleci.com/gh/drush-ops/drush).

## Usage
1. `git clone https://github.com/drush-ops/drush.git`
1. `cd drush`
1. `composer install`
1. Review the configuration settings in [tests/phpunit.xml.dist](https://github.com/drush-ops/drush/blob/10.x/tests/phpunit.xml.dist). 
1. If customization is needed, copy phpunit.xml.dist to phpunit.xml and edit away.
1. Run all test suites: `composer test`

## Docker
Drush's own tests may be run within provided Docker containers (see docker-compose.yml):

- Start containers: `docker-compose up -d`
- Run a test: `docker-compose exec drupal composer functional -- --filter testUserRole`
- To change configuration, copy .env.example to .env, edit to taste, and run `docker-compose up -d` again
- See the [.env.example file](https://github.com/drush-ops/drush/blob/10.x/.env.example) for help on enabling Xdebug.

## Advanced usage
- Run only one test suite
    - `composer unit`
    - `composer integration`
    - `composer functional`
- Run only tests matching a regex: `composer functional -- --filter testUserRole`
- Skip slow tests (usually those with network usage): `composer functional -- --exclude-group slow`
- XML results: `composer functional -- --log-junit results.xml`
- Ad-hoc testing with the SUT
  - `UNISH_DIRTY=1 composer functional -- --filter testUserRole`
  - `./drush @sut.dev status`

## About the Test Suites
- **Unit tests** operate on functions that take values and return results without creating side effects. No database connection is required to run these tests, and no Drupal site is set up.
- **Integration tests** set up a test dependency injection container and operate by calling the Symfony Application APIs directly. A Drupal site called the System Under Test is set up and used for the tests. The SUT is set up and installed only once, and then is re-used for all tests. Integration tests therefore cannot make destructive changes to the SUT database. Also, Drupal is bootstrapped only once (always using the standard Drupal kernel, never the install or update kernel). This means that all commands run at BOOTSTRAP_FULL, and it is not possible to test loading different Drush configuration files and so on. It is not possible to test argument / option parsing. The shutdown and error handlers are not installed, so PHP deprecation warnings will be evidenced in the integration tests.
- **Functional tests** operate by `exec`ing the Drush executable. All functional tests therefore run in their own separate processes. The Drupal System Under Test is set up every time it is needed by any functional test. It is therefore okay if a functional test changes the state of the SUT. The codebase is re-used, so no destructive changes should be made to the code.

## Drush Test Traits
Drush provides test traits that may be used to test your own Drush extensions. Adding the traits varies slightly depending how you package your Drush extension.

  - An extension that ships inside a contributed module - [DevelCommandsTest](https://cgit.drupalcode.org/devel/tree/tests/src/Functional/DevelCommandsTest.php?h=8.x-2.x) for an example. More examples are [SchedulerDrushTest](https://git.drupalcode.org/project/scheduler/blob/8.x-1.x/tests/src/Functional/SchedulerDrushTest.php) and [Views Bulk Operations](https://git.drupalcode.org/project/views_bulk_operations/-/blob/8.x-3.x/tests/src/Functional/DrushCommandsTest.php). Remember to add `drush/drush` to the your modules composer.json (`require-dev` section).
  - A standalone Drush extension or one that ships inside a custom module - [example drush extension](https://github.com/drush-ops/example-drush-extension)

Once you have included the Drush Test Traits, you will be able to write simple tests that call your extension's commands and makes assertions against the output.
```php
    public function testMyCommand()
    {
        $this->drush('my:command', ['param'], ['flag' => 'value']);
        $this->assertOutputEquals('The parameter is "param" and the "flag" option is "value"');
    }
``` 
