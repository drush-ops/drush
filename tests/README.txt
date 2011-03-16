Drush's test suite based on phpunit (http://www.phpunit.de/).

Usage
--------
- Install PHPUnit
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