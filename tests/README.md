Drush's test suite is based on [PHPUnit](http://www.phpunit.de). In order to maintain
high quality, our tests are run on every push by [Travis](https://travis-ci.org/drush-ops/drush)

Usage
--------
1. Review the configuration settings in [tests/phpunit.xml.dist](phpunit.xml.dist). If customization is needed, copy to tests/phpunit.xml and edit away.
1. The tests will create and use a database named `unish_dev` on your database server. 
1. Run unit tests: `unish.clean.php`

Advanced usage
---------
- Run only tests matching a regex: `unish.clean.php --filter=testVersionString`
- Run only tests from a particular test case: `unish.clean.php [className] tests/[filename].php`
- Skip slow tests (usually those with network usage): `unish.clean.php --exclude-group slow`
- XML results: `unish.clean.php --filter=testVersionString --log-junit results.xml`
- Skip rebuild of Site-Under_Test (presumably for speed) - `unish.phpunit.php`
- See the [PHPUnit command line documentation](https://phpunit.de/manual/current/en/textui.html) for more options.
