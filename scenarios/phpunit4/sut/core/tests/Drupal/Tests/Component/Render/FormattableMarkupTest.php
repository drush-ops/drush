<?php

namespace Drupal\Tests\Component\Render;

use Drupal\Component\Render\FormattableMarkup;
use PHPUnit\Framework\TestCase;

/**
 * Tests the TranslatableMarkup class.
 *
 * @coversDefaultClass \Drupal\Component\Render\FormattableMarkup
 * @group utility
 */
class FormattableMarkupTest extends TestCase {

  /**
   * The error message of the last error in the error handler.
   *
   * @var string
   */
  protected $lastErrorMessage;

  /**
   * The error number of the last error in the error handler.
   *
   * @var int
   */
  protected $lastErrorNumber;

  /**
   * @covers ::__toString
   * @covers ::jsonSerialize
   */
  public function testToString() {
    $string = 'Can I please have a @replacement';
    $formattable_string = new FormattableMarkup($string, ['@replacement' => 'kitten']);
    $text = (string) $formattable_string;
    $this->assertEquals('Can I please have a kitten', $text);
    $text = $formattable_string->jsonSerialize();
    $this->assertEquals('Can I please have a kitten', $text);
  }

  /**
   * @covers ::count
   */
  public function testCount() {
    $string = 'Can I please have a @replacement';
    $formattable_string = new FormattableMarkup($string, ['@replacement' => 'kitten']);
    $this->assertEquals(strlen($string), $formattable_string->count());
  }

  /**
   * Custom error handler that saves the last error.
   *
   * We need this custom error handler because we cannot rely on the error to
   * exception conversion as __toString is never allowed to leak any kind of
   * exception.
   *
   * @param int $error_number
   *   The error number.
   * @param string $error_message
   *   The error message.
   */
  public function errorHandler($error_number, $error_message) {
    $this->lastErrorNumber = $error_number;
    $this->lastErrorMessage = $error_message;
  }

  /**
   * @covers ::__toString
   * @dataProvider providerTestUnexpectedPlaceholder
   */
  public function testUnexpectedPlaceholder($string, $arguments, $error_number, $error_message) {
    // We set a custom error handler because of https://github.com/sebastianbergmann/phpunit/issues/487
    set_error_handler([$this, 'errorHandler']);
    // We want this to trigger an error.
    $markup = new FormattableMarkup($string, $arguments);
    // Cast it to a string which will generate the errors.
    $output = (string) $markup;
    restore_error_handler();
    // The string should not change.
    $this->assertEquals($string, $output);
    $this->assertEquals($error_number, $this->lastErrorNumber);
    $this->assertEquals($error_message, $this->lastErrorMessage);
  }

  /**
   * Data provider for FormattableMarkupTest::testUnexpectedPlaceholder().
   *
   * @return array
   */
  public function providerTestUnexpectedPlaceholder() {
    return [
      ['Non alpha starting character: ~placeholder', ['~placeholder' => 'replaced'], E_USER_ERROR, 'Invalid placeholder (~placeholder) in string: Non alpha starting character: ~placeholder'],
      ['Alpha starting character: placeholder', ['placeholder' => 'replaced'], E_USER_DEPRECATED, 'Invalid placeholder (placeholder) in string: Alpha starting character: placeholder'],
      // Ensure that where the placeholder is located in the the string is
      // irrelevant.
      ['placeholder', ['placeholder' => 'replaced'], E_USER_DEPRECATED, 'Invalid placeholder (placeholder) in string: placeholder'],
    ];
  }

}
