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
