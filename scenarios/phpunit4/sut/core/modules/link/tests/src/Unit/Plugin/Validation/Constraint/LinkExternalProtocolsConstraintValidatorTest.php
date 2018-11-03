<?php

namespace Drupal\Tests\link\Unit\Plugin\Validation\Constraint;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\link\Plugin\Validation\Constraint\LinkExternalProtocolsConstraint;
use Drupal\link\Plugin\Validation\Constraint\LinkExternalProtocolsConstraintValidator;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @coversDefaultClass \Drupal\link\Plugin\Validation\Constraint\LinkExternalProtocolsConstraintValidator
 * @group Link
 */
class LinkExternalProtocolsConstraintValidatorTest extends UnitTestCase {

  /**
   * @covers ::validate
   * @dataProvider providerValidate
   */
  public function testValidate($value, $valid) {
    $context = $this->getMock(ExecutionContextInterface::class);

    if ($valid) {
      $context->expects($this->never())
        ->method('addViolation');
    }
    else {
      $context->expects($this->once())
        ->method('addViolation');
    }

    // Setup some more allowed protocols.
    UrlHelper::setAllowedProtocols(['http', 'https', 'magnet']);

    $constraint = new LinkExternalProtocolsConstraint();

    $validator = new LinkExternalProtocolsConstraintValidator();
    $validator->initialize($context);
    $validator->validate($value, $constraint);
  }

  /**
   * Data provider for ::testValidate
   */
  public function providerValidate() {
    $data = [];

    // Test allowed protocols.
    $data[] = ['http://www.drupal.org', TRUE];
    $data[] = ['https://www.drupal.org', TRUE];
    $data[] = ['magnet:?xt=urn:sha1:YNCKHTQCWBTRNJIV4WNAE52SJUQCZO5C', TRUE];

    // Invalid protocols.
    $data[] = ['ftp://ftp.funet.fi/pub/standards/RFC/rfc959.txt', FALSE];

    foreach ($data as &$single_data) {
      $url = Url::fromUri($single_data[0]);
      $link = $this->getMock('Drupal\link\LinkItemInterface');
      $link->expects($this->any())
        ->method('getUrl')
        ->willReturn($url);
      $single_data[0] = $link;
    }

    return $data;
  }

  /**
   * @covers ::validate
   *
   * @see \Drupal\Core\Url::fromUri
   */
  public function testValidateWithMalformedUri() {
    $link = $this->getMock('Drupal\link\LinkItemInterface');
    $link->expects($this->any())
      ->method('getUrl')
      ->willThrowException(new \InvalidArgumentException());

    $context = $this->getMock(ExecutionContextInterface::class);
    $context->expects($this->never())
      ->method('addViolation');

    $constraint = new LinkExternalProtocolsConstraint();

    $validator = new LinkExternalProtocolsConstraintValidator();
    $validator->initialize($context);
    $validator->validate($link, $constraint);
  }

  /**
   * @covers ::validate
   */
  public function testValidateIgnoresInternalUrls() {
    $link = $this->getMock('Drupal\link\LinkItemInterface');
    $link->expects($this->any())
      ->method('getUrl')
      ->willReturn(Url::fromRoute('example.test'));

    $context = $this->getMock(ExecutionContextInterface::class);
    $context->expects($this->never())
      ->method('addViolation');

    $constraint = new LinkExternalProtocolsConstraint();

    $validator = new LinkExternalProtocolsConstraintValidator();
    $validator->initialize($context);
    $validator->validate($link, $constraint);
  }

}
