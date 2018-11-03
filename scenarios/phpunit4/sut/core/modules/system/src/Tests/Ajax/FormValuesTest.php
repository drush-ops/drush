<?php

namespace Drupal\system\Tests\Ajax;

use Drupal\Core\Ajax\DataCommand;

/**
 * Tests that form values are properly delivered to AJAX callbacks.
 *
 * @group Ajax
 */
class FormValuesTest extends AjaxTestBase {

  protected function setUp() {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['access content']));
  }

  /**
   * Submits forms with select and checkbox elements via Ajax.
   */
  public function testSimpleAjaxFormValue() {
    // Verify form values of a select element.
    foreach (['red', 'green', 'blue'] as $item) {
      $edit = [
        'select' => $item,
      ];
      $commands = $this->drupalPostAjaxForm('ajax_forms_test_get_form', $edit, 'select');
      $expected = new DataCommand('#ajax_selected_color', 'form_state_value_select', $item);
      $this->assertCommand($commands, $expected->render(), 'Verification of AJAX form values from a selectbox issued with a correct value.');
    }

    // Verify form values of a checkbox element.
    foreach ([FALSE, TRUE] as $item) {
      $edit = [
        'checkbox' => $item,
      ];
      $commands = $this->drupalPostAjaxForm('ajax_forms_test_get_form', $edit, 'checkbox');
      $expected = new DataCommand('#ajax_checkbox_value', 'form_state_value_select', (int) $item);
      $this->assertCommand($commands, $expected->render(), 'Verification of AJAX form values from a checkbox issued with a correct value.');
    }

    // Verify that AJAX elements with invalid callbacks return error code 500.
    // Ensure the test error log is empty before these tests.
    $this->assertNoErrorsLogged();
    // We don't need to check for the X-Drupal-Ajax-Token header with these
    // invalid requests.
    $this->assertAjaxHeader = FALSE;
    foreach (['null', 'empty', 'nonexistent'] as $key) {
      $element_name = 'select_' . $key . '_callback';
      $edit = [
        $element_name => 'red',
      ];
      $commands = $this->drupalPostAjaxForm('ajax_forms_test_get_form', $edit, $element_name);
      $this->assertResponse(500);
    }
    // Switch this back to the default.
    $this->assertAjaxHeader = TRUE;
    // The exceptions are expected. Do not interpret them as a test failure.
    // Not using File API; a potential error must trigger a PHP warning.
    unlink(\Drupal::root() . '/' . $this->siteDirectory . '/error.log');
  }

}
