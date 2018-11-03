<?php

namespace Drupal\system\Tests\Ajax;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests that AJAX-enabled forms work when multiple instances of the same form
 * are on a page.
 *
 * @group Ajax
 */
class MultiFormTest extends AjaxTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['form_test'];

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Create a multi-valued field for 'page' nodes to use for Ajax testing.
    $field_name = 'field_ajax_test';
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $field_name,
      'type' => 'text',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
    entity_get_form_display('node', 'page', 'default')
      ->setComponent($field_name, ['type' => 'text_textfield'])
      ->save();

    // Log in a user who can create 'page' nodes.
    $this->drupalLogin($this->drupalCreateUser(['create page content']));
  }

  /**
   * Tests that pages with the 'node_page_form' included twice work correctly.
   */
  public function testMultiForm() {
    // HTML IDs for elements within the field are potentially modified with
    // each Ajax submission, but these variables are stable and help target the
    // desired elements.
    $field_name = 'field_ajax_test';

    $form_xpath = '//form[starts-with(@id, "node-page-form")]';
    $field_xpath = '//div[contains(@class, "field--name-field-ajax-test")]';
    $button_name = $field_name . '_add_more';
    $button_value = t('Add another item');
    $button_xpath_suffix = '//input[@name="' . $button_name . '"]';
    $field_items_xpath_suffix = '//input[@type="text"]';

    // Ensure the initial page contains both node forms and the correct number
    // of field items and "add more" button for the multi-valued field within
    // each form.
    $this->drupalGet('form-test/two-instances-of-same-form');

    $fields = $this->xpath($form_xpath . $field_xpath);
    $this->assertEqual(count($fields), 2);
    foreach ($fields as $field) {
      $this->assertEqual(count($field->xpath('.' . $field_items_xpath_suffix)), 1, 'Found the correct number of field items on the initial page.');
      $this->assertFieldsByValue($field->xpath('.' . $button_xpath_suffix), NULL, 'Found the "add more" button on the initial page.');
    }

    $this->assertNoDuplicateIds(t('Initial page contains unique IDs'), 'Other');

    // Submit the "add more" button of each form twice. After each corresponding
    // page update, ensure the same as above.

    for ($i = 0; $i < 2; $i++) {
      $forms = $this->xpath($form_xpath);
      foreach ($forms as $offset => $form) {
        $form_html_id = (string) $form['id'];
        $this->drupalPostAjaxForm(NULL, [], [$button_name => $button_value], NULL, [], [], $form_html_id);
        $form = $this->xpath($form_xpath)[$offset];
        $field = $form->xpath('.' . $field_xpath);

        $this->assertEqual(count($field[0]->xpath('.' . $field_items_xpath_suffix)), $i + 2, 'Found the correct number of field items after an AJAX submission.');
        $this->assertFieldsByValue($field[0]->xpath('.' . $button_xpath_suffix), NULL, 'Found the "add more" button after an AJAX submission.');
        $this->assertNoDuplicateIds(t('Updated page contains unique IDs'), 'Other');
      }
    }
  }

}
