Drush's test suite is based on [PHPUnit](http://www.phpunit.de). In order to maintain
high quality, our tests are run on every push by [Travis](https://travis-ci.org/drush-ops/drush)

Usage
--------
1. Install PHPUnit
    - Follow the [Composer installation instruction](http://getcomposer.org/download)
    - Run composer depending on your installation from the Drush root directory
  
    ```bash
    php composer.phar install --dev
    composer install --dev
    ```
1. Review the configuration settings in phpunit.xml.dist. If customization is needed, copy to phpunit.xml and edit away.
1. Run unit tests:

    ```bash
    $ cd tests
    $ php ../vendor/phpunit/phpunit/phpunit.php .
    ```

Advanced usage
---------
- Run only tests matching a regex: `phpunit --filter=testVersionString`
- Skip slow tests (usually those with network usage): `phpunit --exclude-group slow`
- XML results: `phpunit --filter=testVersionString --log-junit results.xml`
- Use an alternate version of Drupal: `UNISH_DRUPAL_MAJOR_VERSION=8 phpunit ...`

Reuse by Drush Commandfiles
-----------
Drush commandfiles are encouraged to ship with PHPUnit test cases that
extend Drush_UnitTestCase and Drush_CommandTestCase. In order to run
the tests, you have to point to the [drush_testcase.inc](tests/drush_testcase.inc) file
such as `phpunit --bootstrap=/path/to/drush/tests/drush_testcase.inc`.
The devel project does exactly this -
http://drupalcode.org/project/devel.git/blob/refs/heads/8.x-1.x:/develDrushTest.php

Cache
-----------
In order to speed up test runs, Unish (the drush testing class) caches built Drupal sites
and restores them as requested by tests. Once in while, you might need to clear this cache
by deleting the <tmp>/drush-cache directory.

