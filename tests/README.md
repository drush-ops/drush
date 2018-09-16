Drush's test suite is based on [PHPUnit](http://www.phpunit.de). In order to maintain
high quality, our tests are run on every push. 

- Functional tests at [CircleCi](https://circleci.com/gh/drush-ops/drush) 
- Unit tests at [Travis](https://travis-ci.org/drush-ops/drush)
- Coding standards at [Shippable](https://app.shippable.com/github/drush-ops/drush/).

Usage
--------
1. Review the configuration settings in [tests/phpunit.xml.dist](phpunit.xml.dist). If customization is needed, copy to phpunit.xml and edit away.
1. Run test suite: `composer test`

Advanced usage
---------
- Run only tests matching a regex: `composer functional --filter testVersionString`
- Skip slow tests (usually those with network usage): `composer functional --exclude-group slow`
- XML results: `composer functional --filter testVersionString --log-junit results.xml`
