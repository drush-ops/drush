An experimental test suite for Drush (http://drush.ws) based on
phpunit (http://www.phpunit.de/). The intent is to commit this to core Drush.

Usage
--------
- Install PHPUnit
- Copy phpunit.xml.dist to phpunit.xml and customize.
- From the root of this package: `phpunit .` or `runner.php .`

Advanced usage
---------
- Run only tests matching a regex: phpunit --filter=testVersionString .
- XML results: phpunit --filter=testVersionString --log-junit results.xml .

Notes
----------
- I have run tests within Netbeans and it works.
- Speedup downloads with Squid as forward proxy - http://reluctanthacker.rollett.org/node/114.

Feedback
----------
Post comments to http://drupal.org/node/483940. Feel free to fork on Github.

Author
----------
Moshe Weitzman