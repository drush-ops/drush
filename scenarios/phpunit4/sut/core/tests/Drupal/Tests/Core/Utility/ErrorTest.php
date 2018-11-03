<?php

namespace Drupal\Tests\Core\Utility;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Utility\Error;

/**
 * @coversDefaultClass \Drupal\Core\Utility\Error
 * @group Utility
 */
class ErrorTest extends UnitTestCase {

  /**
   * Tests the getLastCaller() method.
   *
   * @param array $backtrace
   *   The test backtrace array.
   * @param array $expected
   *   The expected return array.
   *
   * @dataProvider providerTestGetLastCaller
   */
  public function testGetLastCaller($backtrace, $expected) {
    $this->assertSame($expected, Error::getLastCaller($backtrace));
  }

  /**
   * Data provider for testGetLastCaller.
   *
   * @return array
   *   An array of parameter data.
   */
  public function providerTestGetLastCaller() {
    $data = [];

    // Test with just one item. This should default to the function being
    // main().
    $single_item = [$this->createBacktraceItem()];
    $data[] = [$single_item, $this->createBacktraceItem('main()')];

    // Add a second item, without a class.
    $two_items = $single_item;
    $two_items[] = $this->createBacktraceItem('test_function_two');
    $data[] = [$two_items, $this->createBacktraceItem('test_function_two()')];

    // Add a second item, with a class.
    $two_items = $single_item;
    $two_items[] = $this->createBacktraceItem('test_function_two', 'TestClass');
    $data[] = [$two_items, $this->createBacktraceItem('TestClass->test_function_two()')];

    // Add blacklist functions to backtrace. They should get removed.
    foreach (['debug', '_drupal_error_handler', '_drupal_exception_handler'] as $function) {
      $two_items = $single_item;
      // Push to the start of the backtrace.
      array_unshift($two_items, $this->createBacktraceItem($function));
      $data[] = [$single_item, $this->createBacktraceItem('main()')];
    }

    return $data;
  }

  /**
   * Tests the formatBacktrace() method.
   *
   * @param array $backtrace
   *   The test backtrace array.
   * @param array $expected
   *   The expected return array.
   *
   * @dataProvider providerTestFormatBacktrace
   */
  public function testFormatBacktrace($backtrace, $expected) {
    $this->assertSame($expected, Error::formatBacktrace($backtrace));
  }

  /**
   * Data provider for testFormatBacktrace.
   *
   * @return array
   */
  public function providerTestFormatBacktrace() {
    $data = [];

    // Test with no function, main should be in the backtrace.
    $data[] = [[$this->createBacktraceItem(NULL, NULL)], "main() (Line: 10)\n"];

    $base = [$this->createBacktraceItem()];
    $data[] = [$base, "test_function() (Line: 10)\n"];

    // Add a second item.
    $second_item = $base;
    $second_item[] = $this->createBacktraceItem('test_function_2');

    $data[] = [$second_item, "test_function() (Line: 10)\ntest_function_2() (Line: 10)\n"];

    // Add a second item, with a class.
    $second_item_class = $base;
    $second_item_class[] = $this->createBacktraceItem('test_function_2', 'TestClass');

    $data[] = [$second_item_class, "test_function() (Line: 10)\nTestClass->test_function_2() (Line: 10)\n"];

    // Add a second item, with a class.
    $second_item_args = $base;
    $second_item_args[] = $this->createBacktraceItem('test_function_2', NULL, ['string', 10, new \stdClass()]);

    $data[] = [$second_item_args, "test_function() (Line: 10)\ntest_function_2('string', 10, Object) (Line: 10)\n"];

    return $data;
  }

  /**
   * Creates a mock backtrace item.
   *
   * @param string|null $function
   *   (optional) The function name to use in the backtrace item.
   * @param string $class
   *   (optional) The class to use in the backtrace item.
   * @param array $args
   *   (optional) An array of function arguments to add to the backtrace item.
   * @param int $line
   *   (optional) The line where the function was called.
   *
   * @return array
   *   A backtrace array item.
   */
  protected function createBacktraceItem($function = 'test_function', $class = NULL, array $args = [], $line = 10) {
    $backtrace = [
      'file' => 'test_file',
      'line' => $line,
      'function' => $function,
      'args' => [],
    ];

    if (isset($class)) {
      $backtrace['class'] = $class;
      $backtrace['type'] = '->';
    }

    if (!empty($args)) {
      $backtrace['args'] = $args;
    }

    return $backtrace;
  }

}
