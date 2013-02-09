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
   * Test various output formats.
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
        'expected' => "array (
  'a' =>
  array (
    'b' => 2,
    'c' => 3,
  ),
  'd' =>
  array (
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
        'expected' => "\$config['a'] = array (
  'b' => 2,
  'c' => 3,
);
\$config['d'] = array (
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
        'expected' => "\$a['b'] = 2;
\$a['c'] = 3;
\$d['e'] = 5;
\$d['f'] = 6;",
      ),
    );
  }
}
