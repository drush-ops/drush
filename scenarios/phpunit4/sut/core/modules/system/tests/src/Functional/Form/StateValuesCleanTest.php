<?php

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests proper removal of submitted form values using
 * \Drupal\Core\Form\FormState::cleanValues().
 *
 * @group Form
 */
class StateValuesCleanTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['form_test'];

  /**
   * Tests \Drupal\Core\Form\FormState::cleanValues().
   */
  public function testFormStateValuesClean() {
    $this->drupalPostForm('form_test/form-state-values-clean', [], t('Submit'));
    $values = Json::decode($this->getSession()->getPage()->getContent());

    // Setup the expected result.
    $result = [
      'beer' => 1000,
      'baz' => ['beer' => 2000],
    ];

    // Verify that all internal Form API elements were removed.
    $this->assertFalse(isset($values['form_id']), format_string('%element was removed.', ['%element' => 'form_id']));
    $this->assertFalse(isset($values['form_token']), format_string('%element was removed.', ['%element' => 'form_token']));
    $this->assertFalse(isset($values['form_build_id']), format_string('%element was removed.', ['%element' => 'form_build_id']));
    $this->assertFalse(isset($values['op']), format_string('%element was removed.', ['%element' => 'op']));

    // Verify that all buttons were removed.
    $this->assertFalse(isset($values['foo']), format_string('%element was removed.', ['%element' => 'foo']));
    $this->assertFalse(isset($values['bar']), format_string('%element was removed.', ['%element' => 'bar']));
    $this->assertFalse(isset($values['baz']['foo']), format_string('%element was removed.', ['%element' => 'foo']));
    $this->assertFalse(isset($values['baz']['baz']), format_string('%element was removed.', ['%element' => 'baz']));

    // Verify values manually added for cleaning were removed.
    $this->assertFalse(isset($values['wine']), new FormattableMarkup('%element was removed.', ['%element' => 'wine']));

    // Verify that nested form value still exists.
    $this->assertTrue(isset($values['baz']['beer']), 'Nested form value still exists.');

    // Verify that actual form values equal resulting form values.
    $this->assertEqual($values, $result, 'Expected form values equal actual form values.');
  }

}
