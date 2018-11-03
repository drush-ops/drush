<?php

namespace Drupal\Tests\Core\StringTranslation;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the TranslatableMarkup class.
 *
 * @coversDefaultClass \Drupal\Core\StringTranslation\TranslatableMarkup
 * @group StringTranslation
 */
class TranslatableMarkupTest extends UnitTestCase {

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
   * Tests that errors are correctly handled when a __toString() fails.
   *
   * @covers ::__toString
   */
  public function testToString() {
    $translation = $this->getMock(TranslationInterface::class);

    $string = 'May I have an exception please?';
    $text = $this->getMockBuilder(TranslatableMarkup::class)
      ->setConstructorArgs([$string, [], [], $translation])
      ->setMethods(['_die'])
      ->getMock();
    $text
      ->expects($this->once())
      ->method('_die')
      ->willReturn('');

    $translation
      ->method('translateString')
      ->with($text)
      ->willReturnCallback(function () {
        throw new \Exception('Yes you may.');
      });

    // We set a custom error handler because of https://github.com/sebastianbergmann/phpunit/issues/487
    set_error_handler([$this, 'errorHandler']);
    // We want this to trigger an error.
    (string) $text;
    restore_error_handler();

    $this->assertEquals(E_USER_ERROR, $this->lastErrorNumber);
    $this->assertRegExp('/Exception thrown while calling __toString on a .*Mock_TranslatableMarkup_.* object in .*TranslatableMarkupTest.php on line [0-9]+: Yes you may./', $this->lastErrorMessage);
  }

  /**
   * @covers ::__construct
   */
  public function testIsStringAssertion() {
    $translation = $this->getStringTranslationStub();
    $this->setExpectedException(\InvalidArgumentException::class, '$string ("foo") must be a string.');
    new TranslatableMarkup(new TranslatableMarkup('foo', [], [], $translation));
  }

  /**
   * @covers ::__construct
   */
  public function testIsStringAssertionWithFormattableMarkup() {
    $formattable_string = new FormattableMarkup('@bar', ['@bar' => 'foo']);
    $this->setExpectedException(\InvalidArgumentException::class, '$string ("foo") must be a string.');
    new TranslatableMarkup($formattable_string);
  }

}
