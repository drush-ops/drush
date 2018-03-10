Drush's test suite is based on [PHPUnit](http://www.phpunit.de). In order to maintain
high quality, our tests are run on every push by [Travis](https://travis-ci.org/drush-ops/drush)

Usage
--------
1. Review the configuration settings in [tests/phpunit.xml.dist](phpunit.xml.dist). If customization is needed, copy to phpunit.xml and edit away.
1. Build the Site Under Test: `unish.sut.php`
1. Run test suite: `unish.phpunit.php`

Advanced usage
---------
- Run only tests matching a regex: `unish.phpunit.php --filter=testVersionString`
- Skip slow tests (usually those with network usage): `unish.phpunit.php --exclude-group slow`
- XML results: `unish.phpunit.php --filter=testVersionString --log-junit results.xml`
- Build the SUT and run test suite (slower) - `unish.clean.php`
- Install the SUT in a given folder - `UNISH_TMP=/path/to/folder php unish.sut.php`

Testing contrib module commands
---------

**Important:** The Unish tests should be placed under `tests/src/Unish` in your module. But if you still prefer other location, adjust the settings in `tests/phpunit.xml`.

1. Create a copy of `tests/composer.json.dist` as `tests/composer.json`:
    ```
    cp tests/composer.json.dist tests/composer.json
    ```
1. Add the modules containing the Drush commands you want to test as composer dependencies in `tests/composer.json`. For example:
    ```
    "require": {
        "drupal/migrate_plus": "*"
    }
    ```
    Tip: If you're currently designing the module commands and you want to test your work, you can install the module as a symlink to your local directory where the development is taking place:
    ```
    "require": {
        "drupal/migrate_plus": "*"
    }
    "repositories": [
        {
          "type": "path",
          "url": "/path/to/drupal/project/modules/contrib/migrate_plus",
          "options": {
              "symlink": true
          }
        }
    ]
    ```
1. Follow the Usage section to setup your testing site and run the tests.
