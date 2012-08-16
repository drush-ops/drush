Drush's test suite based on PHPUnit (http://www.phpunit.de/).

Usage
--------
- Install PHPUnit [*]
- Review the configuration settings in phpunit.xml.dist. If customization is needed, copy and rename to phpunit.xml and edit away.
- From the /tests subdirectory, run `phpunit .`

Advanced usage
---------
- Run only tests matching a regex: phpunit --filter=testVersionString .
- Skip slow tests (usually those with network usage): phpunit --exclude-group slow .
- XML results: phpunit --filter=testVersionString --log-junit results.xml .

Reuse by Drush Commandfiles
-----------
Drush commandfiles are encouraged to ship with PHPUnit test cases that
extend Drush_UnitTestCase and Drush_CommandTestCase. In order to run
the tests, you have to point to the /tests/drush_testcase.inc file
such as `phpunit --bootstrap=/path/to/drush/tests/drush_testcase.inc`.
The devel project does exactly this -
http://drupalcode.org/project/devel.git/blob/refs/heads/8.x-1.x:/develDrushTest.php

Cache
-----------
In order to speed up test runs, Unish (the drush testing class) caches built Drupal sites
and restores them as requested by tests. Once in while, you might need to clear this cache
by deleting the <tmp>/drush-cache directory.

[*] Install PHPUnit:
---------------

Drush requires PHPUnit 3.5 or later; installing with PEAR is easiest.
 
On Linux/OSX:
---------

  sudo apt-get install php5-curl php-pear
  sudo pear upgrade --force PEAR
  sudo pear channel-discover pear.phpunit.de
  sudo pear channel-discover components.ez.no
  sudo pear channel-discover pear.symfony-project.com
  sudo pear install --alldeps phpunit/PHPUnit

On Windows:
-----------

Download and save from go-pear.phar http://pear.php.net/go-pear.phar

  php -q go-pear.phar
  pear channel-discover pear.phpunit.de
  pear channel-discover components.ez.no
  pear channel-discover pear.symfony-project.com
  pear install --alldeps phpunit/PHPUnit
