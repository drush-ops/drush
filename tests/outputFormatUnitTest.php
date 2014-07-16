<?php

/**
 * @file
 *   Tests for outputformat.drush.inc
 */

namespace Unish;

class outputFormatUnitCase extends UnitUnishTestCase {

/**
 * Test various output formats using php-eval with no Drupal site.
 *
 * @dataProvider provider
 **/
  public function testOutputFormat($name, $format, $data, $expected) {
    drush_preflight();
    $this->assertEquals($expected, trim(drush_format($data, array(), $format)), $name . ': '. $format);
  }

  public function provider() {
    $json = '{"a":{"b":2,"c":3},"d":{"e":5,"f":6}}';
    if (version_compare(phpversion(), '5.4.0', '>=')) {
      $json = json_encode(json_decode($json), JSON_PRETTY_PRINT);
    }

    return array(
      array(
        'name' => 'String test',
        'format' => 'string',
        'data' => array('drush version' => '6.0-dev'),
        'expected' => '6.0-dev',
      ),
      array(
        'name' => 'List test',
        'format' => 'list',
        'data' => array('drush version' => '6.0-dev'),
        'expected' => '6.0-dev',
      ),
      array(
        'name' => 'Key-value test',
        'format' => 'key-value',
        'data' => array('drush version' => '6.0-dev'),
        'expected' => 'drush version   :  6.0-dev',
      ),
//      array(
//        'name' => 'Table test',
//        'format' => 'table',
//        'data' => array(
//          'a' => array('b' => 2, 'c' => 3),
//          'd' => array('b' => 5, 'c' => 6),
//        ),
//        'expected' => "b  c
// 2  3
// 5  6",
//        ),
      array(
        'name' => 'print-r test',
        'format' => 'print-r',
        'data' => array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('b' => 5, 'c' => 6),
        ),
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
        'data' => array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6),
        ),
        'expected' => $json,
      ),
//      array(
//        'name' => 'key-value test 1d array',
//        'format' => 'key-value',
//        'data' => array(
//          'b' => 'Two B or ! Two B, that is the comparison',
//          'c' => 'I see that C has gone to Sea',
//        ),
//        'expected' => "b   :  Two B or ! Two B, that is the comparison
// c   :  I see that C has gone to Sea",
//      ),
//      array(
//        'name' => 'key-value test 2d array',
//        'format' => 'key-value',
//        'data' => array(
//          'a' => array(
//            'b' => 'Two B or ! Two B, that is the comparison',
//            'c' => 'I see that C has gone to Sea',
//          ),
//          'd' => array(
//            'e' => 'Elephants and electron microscopes',
//            'f' => 'My margin is too small',
//          )
//        ),
//        'expected' => "a   :  Two B or ! Two B, that is the comparison
//        I see that C has gone to Sea
// d   :  Elephants and electron microscopes
//        My margin is too small",
//      ),
      array(
        'name' => 'export test',
        'format' => 'var_export',
        'data' => array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6),
        ),
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
//      array(
//        'name' => 'config test',
//        'format' => 'config',
//        'data' => array(
//          'a' => array('b' => 2, 'c' => 3),
//          'd' => array('e' => 5, 'f' => 6),
//        ),
//        'expected' => "\$config[\"a\"] = array (
//  'b' => 2,
//  'c' => 3,
//);
//\$config[\"d\"] = array (
//  'e' => 5,
//  'f' => 6,
//);",
//      ),
      array(
        'name' => 'variables test',
        'format' => 'variables',
        'data' => array(
          'a' => array('b' => 2, 'c' => 3),
          'd' => array('e' => 5, 'f' => 6),
        ),
        'expected' => "\$a[\"b\"] = 2;
\$a[\"c\"] = 3;
\$d[\"e\"] = 5;
\$d[\"f\"] = 6;",
      ),
    );
  }
}
