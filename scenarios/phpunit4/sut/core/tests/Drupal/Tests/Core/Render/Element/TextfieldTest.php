<?php

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Render\Element\Textfield
 * @group Render
 */
class TextfieldTest extends UnitTestCase {

  /**
   * @covers ::valueCallback
   *
   * @dataProvider providerTestValueCallback
   */
  public function testValueCallback($expected, $input) {
    $element = [];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, Textfield::valueCallback($element, $input, $form_state));
  }

  /**
   * Data provider for testValueCallback().
   */
  public function providerTestValueCallback() {
    $data = [];
    $data[] = [NULL, FALSE];
    $data[] = [NULL, NULL];
    $data[] = ['', ['test']];
    $data[] = ['test', 'test'];
    $data[] = ['123', 123];
    $data[] = ['testwithnewline', "test\nwith\rnewline"];

    return $data;
  }

}
