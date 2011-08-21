Drush's test suite based on phpunit (http://www.phpunit.de/).

Usage
--------
- Install PHPUnit [*]
- Optional. Copy phpunit.xml.dist to phpunit.xml and customize if needed.
- From the /tests subdirectory, run `phpunit .` or `runner.php .`

Advanced usage
---------
- Run only tests matching a regex: phpunit --filter=testVersionString .
- XML results: phpunit --filter=testVersionString --log-junit results.xml .

Notes
----------
- I have run tests within Netbeans and it works.
- Speedup downloads with Squid as forward proxy - http://reluctanthacker.rollett.org/node/114.



[*] Install PHPUnit:

Drush requires PHPUnit 3.5 or later; installing with PEAR is easiest.
 
On Linux:
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
