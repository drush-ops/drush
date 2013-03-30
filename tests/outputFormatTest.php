<?php

/*
 * @file
 *   Tests for role.drush.inc
 */

/*
 *  @group slow
 *  @group commands
 */
class outputFormatCase extends Drush_CommandTestCase {

  /*
   * Test various output formats using php-eval with no Drupal site.
   */
  public function testOutputFormat() {
    $testdata = $this->getTestData();
    $options = array();
    foreach ($testdata as $test) {
      $name = $test['name'] . ": ";
      $expected = $test['expected'];
      $this->drush('php-eval', array($test['code']), $options + array('format' => $test['format']));
      $output = trim($this->getOutput()); // note: we consider trailing eols insignificant
      $this->assertEquals($name . $expected, $name. $output);
    }
  }

  /*
   * Test various output formats using various Drush commands on a Drupal site.
   */
  public function testOutputFormatWithDrupal() {
    $sites = $this->setUpDrupal(1, TRUE);
    $testdata = $this->getTestDataForDrupal();
    $options = array(
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    $this->drush('pm-download', array('devel'), $options);
    foreach ($testdata as $test) {
      $test += array(
        'command' => 'php-eval',
        'args' => array(),
        'options' => array(),
        'format' => 'export',
        'output-filter' => array(),
      );
      $name = $test['name'] . ": ";
      $expected = $test['expected'];
      // We need to specify a fixed column width so that word wrapping does
      // not change our output contrary to our expectations when run in
      // a narrow terminal window.
      $env = array(
        'COLUMNS' => '120',
      );
      $this->drush($test['command'], $test['args'], $options + $test['options'] + array('format' => $test['format']), NULL, NULL, self::EXIT_SUCCESS, NULL, $env);
      $output = trim($this->getOutput()); // note: we consider trailing eols insignificant
      // If the Drupal command we are running might produce variable output,
      // we can use one or more output filters to simplify the output down
      // to an invariant form.
      foreach ($test['output-filter'] as $regex => $replacement) {
        $output = preg_replace($regex, $replacement, $output);
      }
      $this->assertEquals($name . $expected, $name. $output);
    }
  }

  public function getTestDataForDrupal() {
    return array(
      array(
        'name' => 'Status test - drush version / ini',
        'command' => 'core-status',
        'args' => array('drush version'),
        'format' => 'ini',
        'output-filter' => array('/[0-9]+\.[0-9]+-dev/' => '0.0-dev', ),
        'expected' => '0.0-dev',
      ),
      array(
        'name' => 'Status test - drush / ini',
        'command' => 'core-status',
        'args' => array('drush'),
        'format' => 'ini',
        'output-filter' => array('/[0-9]+\.[0-9]+-dev/' => '0.0-dev', '#/.*/etc/drush#' => '/etc/drush'),
        'expected' => 'drush-version=0.0-dev
drush-conf=
drush-alias-files=/etc/drush/dev.alias.drushrc.php',
      ),
      array(
        'name' => 'Status test - drush / export',
        'command' => 'core-status',
        'args' => array('drush'),
        'format' => 'export',
        'output-filter' => array('/[0-9]+\.[0-9]+-dev/' => '0.0-dev', '#/.*/etc/drush#' => '/etc/drush'),
        'expected' => "array(
  'drush-version' => '0.0-dev',
  'drush-conf' => array(),
  'drush-alias-files' => array(
    '/etc/drush/dev.alias.drushrc.php',
  ),
)",
      ),
      array(
        'name' => 'Status test - drush / key-value',
        'command' => 'core-status',
        'args' => array('drush'),
        'format' => 'key-value',
        'output-filter' => array('/[0-9]+\.[0-9]+-dev/' => '0.0-dev', '#/.*/etc/drush#' => '/etc/drush'),
        'expected' => "Drush version         :  0.0-dev
 Drush configuration   :
 Drush alias files     :  /etc/drush/dev.alias.drushrc.php",
      ),
      /*
        core-requirements is a little hard to test, because the
        output can be quite variable

      array(
        'name' => 'Requirements test - table',
        'command' => 'core-requirements',
        'args' => array(),
        'format' => 'table',
        'output-filter' => array(),
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-updatestatus - table',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'table',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "Name    Installed Version  Proposed version  Message
 Drupal  0.0               0.0              Up to date",
      ),
      /*
        pm-updatestatus --format=csv does not work.

        should filter out the items that do not need updating, and print just
        the name of the projects that need updates

      array(
        'name' => 'pm-updatestatus - csv',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'csv',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-updatestatus - csv-list',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'csv-list',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "Drupal,0.0,0.0,Up to date",
      ),
      /*
        pm-updatestatus --format=ini does not work

      array(
        'name' => 'pm-updatestatus - ini',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'ini',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-updatestatus - ini-sections',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'ini-sections',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "[drupal]
name=Drupal
installed_version=0.0
proposed_version=0.0
message=Up to date",
      ),
      /*
        pm-updatestatus --format=key-value does not work

      array(
        'name' => 'pm-updatestatus - key-value',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'key-value',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-updatestatus - key-value-list',
        'command' => 'pm-updatestatus',
        'args' => array(),
        'format' => 'key-value-list',
        'output-filter' => array('/[0-9]+\.[0-9]+/' => '0.0'),
        'expected' => "Name                :  Drupal
 Installed Version   :  0.0
 Proposed version    :  0.0
 Message             :  Up to date",
      ),
      array(
        'name' => 'pm-info - key-value-list',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'key-value-list',
        'expected' => "Project       :  devel
 Type          :  module
 Description   :  Various blocks, pages, and functions for developers.",
      ),
      array(
        'name' => 'pm-info - csv',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'csv',
        'expected' => "devel",
      ),
      array(
        'name' => 'pm-info - csv-list',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'csv-list',
        'expected' => "devel,module,\"Various blocks, pages, and functions for developers.\"",
      ),
      /*
         pm-info --format=ini does not work.

         The output data could not be processed by the selected format 'string'.
         Multiple rows provided where only one is allowed.

      array(
        'name' => 'pm-info - ini',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'ini',
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-info - ini-sections',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'ini-sections',
        'expected' => "[devel]
project=devel
type=module
description=Various blocks, pages, and functions for developers.",
      ),
      /*
        pm-info --format=key-value does not respect --fields

      array(
        'name' => 'pm-info - key-value',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,devel,description'),
        'format' => 'key-value',
        'expected' => "",
      ),
      */
      array(
        'name' => 'pm-info - table',
        'command' => 'pm-info',
        'args' => array('devel'),
        'options' => array('fields' => 'project,type,description'),
        'format' => 'table',
        'expected' => "Project  Type    Description
 devel    module  Various blocks, pages, and functions for developers.",
      ),

      // More commands that also support output formats:

      // pm-list
      // queue-list
      // cache-get
      // config-get and config-list (D8 only)
      // field-info
      // php-eval
      // role-list
      // search-status
      // site-alias
      // user-information
      // variable-get
      // version
      // watchdog-list
      // watchdog-show

    );
  }

  public function getTestData() {
    return array(
      array(
        'name' => 'String test',
        'format' => 'string',
        'code' => "return array('drush version' => '6.0-dev')",
        'expected' => '6.0-dev',
      ),
      array(
        'name' => 'List test',
        'format' => 'list',
        'code' => "return array('drush version' => '6.0-dev')",
        'expected' => '6.0-dev',
      ),
      array(
        'name' => 'Key-value test',
        'format' => 'key-value',
        'code' => "return array('drush version' => '6.0-dev')",
        'expected' => 'drush version   :  6.0-dev',
      ),
      array(
        'name' => 'Table test',
        'format' => 'table',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('b' => 5, 'c' => 6));",
        'expected' => "b  c
 2  3
 5  6",
      ),
      array(
        'name' => 'print-r test',
        'format' => 'print-r',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('b' => 5, 'c' => 6));",
        'expected' => "Array
(
    [a] => Array
        (
            [b] => 2
            [c] => 3
        )

    [d] => Array
        (
            [b] => 5
            [c] => 6
        )

)",
      ),
      array(
        'name' => 'json test',
        'format' => 'json',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6));",
        'expected' => '{"a":{"b":2,"c":3},"d":{"e":5,"f":6}}',
      ),
      array(
        'name' => 'ini test',
        'format' => 'ini',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6));",
        'expected' => "a=2 3
d=5 6",
      ),
      array(
        'name' => 'ini-list test',
        'format' => 'ini-sections',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6));",
        'expected' => "[a]
b=2
c=3

[d]
e=5
f=6",
      ),
      array(
        'name' => 'key-value test 1d array',
        'format' => 'key-value',
        'code' => "return array(
          'b' => 'Two B or ! Two B, that is the comparison',
          'c' => 'I see that C has gone to Sea');",
        'expected' => "b   :  Two B or ! Two B, that is the comparison
 c   :  I see that C has gone to Sea",
      ),
      array(
        'name' => 'key-value test 2d array',
        'format' => 'key-value',
        'code' => "return array(
          'a' => array(
            'b' => 'Two B or ! Two B, that is the comparison',
            'c' => 'I see that C has gone to Sea',
          ),
          'd' => array(
            'e' => 'Elephants and electron microscopes',
            'f' => 'My margin is too small',
          ));",
        'expected' => "a   :  Two B or ! Two B, that is the comparison
        I see that C has gone to Sea
 d   :  Elephants and electron microscopes
        My margin is too small",
      ),
      array(
        'name' => 'export test',
        'format' => 'export',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6));",
        'expected' => "array(
  'a' => array(
    'b' => 2,
    'c' => 3,
  ),
  'd' => array(
    'e' => 5,
    'f' => 6,
  ),
)",
      ),
      array(
        'name' => 'config test',
        'format' => 'config',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6));",
        'expected' => "\$config[\"a\"] = array (
  'b' => 2,
  'c' => 3,
);
\$config[\"d\"] = array (
  'e' => 5,
  'f' => 6,
);",
      ),
      array(
        'name' => 'variables test',
        'format' => 'variables',
        'code' => "return array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6));",
        'expected' => "\$a[\"b\"] = 2;
\$a[\"c\"] = 3;
\$d[\"e\"] = 5;
\$d[\"f\"] = 6;",
      ),
    );
  }
}
