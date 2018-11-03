<?php

namespace Drupal\Tests\options\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Functional\FieldTestBase;

/**
 * Tests the Options field UI functionality.
 *
 * @group options
 */
class OptionsFieldUITest extends FieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'options', 'field_test', 'taxonomy', 'field_ui'];

  /**
   * The name of the created content type.
   *
   * @var string
   */
  protected $typeName;

  /**
   * Machine name of the created content type.
   *
   * @var string
   */
  protected $type;

  /**
   * Name of the option field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Admin path to manage field storage settings.
   *
   * @var string
   */
  protected $adminPath;

  protected function setUp() {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser(['access content', 'administer taxonomy', 'access administration pages', 'administer site configuration', 'administer content types', 'administer nodes', 'bypass node access', 'administer node fields', 'administer node display']);
    $this->drupalLogin($admin_user);

    // Create content type, with underscores.
    $this->typeName = 'test_' . strtolower($this->randomMachineName());
    $type = $this->drupalCreateContentType(['name' => $this->typeName, 'type' => $this->typeName]);
    $this->type = $type->id();
  }

  /**
   * Options (integer) : test 'allowed values' input.
   */
  public function testOptionsAllowedValuesInteger() {
    $this->fieldName = 'field_options_integer';
    $this->createOptionsField('list_integer');

    // Flat list of textual values.
    $string = "Zero\nOne";
    $array = ['0' => 'Zero', '1' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Unkeyed lists are accepted.');
    // Explicit integer keys.
    $string = "0|Zero\n2|Two";
    $array = ['0' => 'Zero', '2' => 'Two'];
    $this->assertAllowedValuesInput($string, $array, 'Integer keys are accepted.');
    // Check that values can be added and removed.
    $string = "0|Zero\n1|One";
    $array = ['0' => 'Zero', '1' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Values can be added and removed.');
    // Non-integer keys.
    $this->assertAllowedValuesInput("1.1|One", 'keys must be integers', 'Non integer keys are rejected.');
    $this->assertAllowedValuesInput("abc|abc", 'keys must be integers', 'Non integer keys are rejected.');
    // Mixed list of keyed and unkeyed values.
    $this->assertAllowedValuesInput("Zero\n1|One", 'invalid input', 'Mixed lists are rejected.');

    // Create a node with actual data for the field.
    $settings = [
      'type' => $this->type,
      $this->fieldName => [['value' => 1]],
    ];
    $node = $this->drupalCreateNode($settings);

    // Check that a flat list of values is rejected once the field has data.
    $this->assertAllowedValuesInput("Zero\nOne", 'invalid input', 'Unkeyed lists are rejected once the field has data.');

    // Check that values can be added but values in use cannot be removed.
    $string = "0|Zero\n1|One\n2|Two";
    $array = ['0' => 'Zero', '1' => 'One', '2' => 'Two'];
    $this->assertAllowedValuesInput($string, $array, 'Values can be added.');
    $string = "0|Zero\n1|One";
    $array = ['0' => 'Zero', '1' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Values not in use can be removed.');
    $this->assertAllowedValuesInput("0|Zero", 'some values are being removed while currently in use', 'Values in use cannot be removed.');

    // Delete the node, remove the value.
    $node->delete();
    $string = "0|Zero";
    $array = ['0' => 'Zero'];
    $this->assertAllowedValuesInput($string, $array, 'Values not in use can be removed.');

    // Check that the same key can only be used once.
    $string = "0|Zero\n0|One";
    $array = ['0' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Same value cannot be used multiple times.');
  }

  /**
   * Options (float) : test 'allowed values' input.
   */
  public function testOptionsAllowedValuesFloat() {
    $this->fieldName = 'field_options_float';
    $this->createOptionsField('list_float');

    // Flat list of textual values.
    $string = "Zero\nOne";
    $array = ['0' => 'Zero', '1' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Unkeyed lists are accepted.');
    // Explicit numeric keys.
    $string = "0|Zero\n.5|Point five";
    $array = ['0' => 'Zero', '0.5' => 'Point five'];
    $this->assertAllowedValuesInput($string, $array, 'Integer keys are accepted.');
    // Check that values can be added and removed.
    $string = "0|Zero\n.5|Point five\n1.0|One";
    $array = ['0' => 'Zero', '0.5' => 'Point five', '1' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Values can be added and removed.');
    // Non-numeric keys.
    $this->assertAllowedValuesInput("abc|abc\n", 'each key must be a valid integer or decimal', 'Non numeric keys are rejected.');
    // Mixed list of keyed and unkeyed values.
    $this->assertAllowedValuesInput("Zero\n1|One\n", 'invalid input', 'Mixed lists are rejected.');

    // Create a node with actual data for the field.
    $settings = [
      'type' => $this->type,
      $this->fieldName => [['value' => .5]],
    ];
    $node = $this->drupalCreateNode($settings);

    // Check that a flat list of values is rejected once the field has data.
    $this->assertAllowedValuesInput("Zero\nOne", 'invalid input', 'Unkeyed lists are rejected once the field has data.');

    // Check that values can be added but values in use cannot be removed.
    $string = "0|Zero\n.5|Point five\n2|Two";
    $array = ['0' => 'Zero', '0.5' => 'Point five', '2' => 'Two'];
    $this->assertAllowedValuesInput($string, $array, 'Values can be added.');
    $string = "0|Zero\n.5|Point five";
    $array = ['0' => 'Zero', '0.5' => 'Point five'];
    $this->assertAllowedValuesInput($string, $array, 'Values not in use can be removed.');
    $this->assertAllowedValuesInput("0|Zero", 'some values are being removed while currently in use', 'Values in use cannot be removed.');

    // Delete the node, remove the value.
    $node->delete();
    $string = "0|Zero";
    $array = ['0' => 'Zero'];
    $this->assertAllowedValuesInput($string, $array, 'Values not in use can be removed.');

    // Check that the same key can only be used once.
    $string = "0.5|Point five\n0.5|Half";
    $array = ['0.5' => 'Half'];
    $this->assertAllowedValuesInput($string, $array, 'Same value cannot be used multiple times.');

    // Check that different forms of the same float value cannot be used.
    $string = "0|Zero\n.5|Point five\n0.5|Half";
    $array = ['0' => 'Zero', '0.5' => 'Half'];
    $this->assertAllowedValuesInput($string, $array, 'Different forms of the same value cannot be used.');
  }

  /**
   * Options (text) : test 'allowed values' input.
   */
  public function testOptionsAllowedValuesText() {
    $this->fieldName = 'field_options_text';
    $this->createOptionsField('list_string');

    // Flat list of textual values.
    $string = "Zero\nOne";
    $array = ['Zero' => 'Zero', 'One' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Unkeyed lists are accepted.');
    // Explicit keys.
    $string = "zero|Zero\none|One";
    $array = ['zero' => 'Zero', 'one' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Explicit keys are accepted.');
    // Check that values can be added and removed.
    $string = "zero|Zero\ntwo|Two";
    $array = ['zero' => 'Zero', 'two' => 'Two'];
    $this->assertAllowedValuesInput($string, $array, 'Values can be added and removed.');
    // Mixed list of keyed and unkeyed values.
    $string = "zero|Zero\nOne\n";
    $array = ['zero' => 'Zero', 'One' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Mixed lists are accepted.');
    // Overly long keys.
    $this->assertAllowedValuesInput("zero|Zero\n" . $this->randomMachineName(256) . "|One", 'each key must be a string at most 255 characters long', 'Overly long keys are rejected.');

    // Create a node with actual data for the field.
    $settings = [
      'type' => $this->type,
      $this->fieldName => [['value' => 'One']],
    ];
    $node = $this->drupalCreateNode($settings);

    // Check that flat lists of values are still accepted once the field has
    // data.
    $string = "Zero\nOne";
    $array = ['Zero' => 'Zero', 'One' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Unkeyed lists are still accepted once the field has data.');

    // Check that values can be added but values in use cannot be removed.
    $string = "Zero\nOne\nTwo";
    $array = ['Zero' => 'Zero', 'One' => 'One', 'Two' => 'Two'];
    $this->assertAllowedValuesInput($string, $array, 'Values can be added.');
    $string = "Zero\nOne";
    $array = ['Zero' => 'Zero', 'One' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Values not in use can be removed.');
    $this->assertAllowedValuesInput("Zero", 'some values are being removed while currently in use', 'Values in use cannot be removed.');

    // Delete the node, remove the value.
    $node->delete();
    $string = "Zero";
    $array = ['Zero' => 'Zero'];
    $this->assertAllowedValuesInput($string, $array, 'Values not in use can be removed.');

    // Check that string values with dots can be used.
    $string = "Zero\nexample.com|Example";
    $array = ['Zero' => 'Zero', 'example.com' => 'Example'];
    $this->assertAllowedValuesInput($string, $array, 'String value with dot is supported.');

    // Check that the same key can only be used once.
    $string = "zero|Zero\nzero|One";
    $array = ['zero' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Same value cannot be used multiple times.');
  }

  /**
   * Options (text) : test 'trimmed values' input.
   */
  public function testOptionsTrimmedValuesText() {
    $this->fieldName = 'field_options_trimmed_text';
    $this->createOptionsField('list_string');

    // Explicit keys.
    $string = "zero |Zero\none | One";
    $array = ['zero' => 'Zero', 'one' => 'One'];
    $this->assertAllowedValuesInput($string, $array, 'Explicit keys are accepted and trimmed.');
  }

  /**
   * Helper function to create list field of a given type.
   *
   * @param string $type
   *   One of 'list_integer', 'list_float' or 'list_string'.
   */
  protected function createOptionsField($type) {
    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => $type,
    ])->save();
    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'bundle' => $this->type,
    ])->save();

    entity_get_form_display('node', $this->type, 'default')->setComponent($this->fieldName)->save();

    $this->adminPath = 'admin/structure/types/manage/' . $this->type . '/fields/node.' . $this->type . '.' . $this->fieldName . '/storage';
  }

  /**
   * Tests a string input for the 'allowed values' form element.
   *
   * @param $input_string
   *   The input string, in the pipe-linefeed format expected by the form
   *   element.
   * @param $result
   *   Either an expected resulting array in
   *   $field->getSetting('allowed_values'), or an expected error message.
   * @param $message
   *   Message to display.
   */
  public function assertAllowedValuesInput($input_string, $result, $message) {
    $edit = ['settings[allowed_values]' => $input_string];
    $this->drupalPostForm($this->adminPath, $edit, t('Save field settings'));
    $this->assertNoRaw('&amp;lt;', 'The page does not have double escaped HTML tags.');

    if (is_string($result)) {
      $this->assertText($result, $message);
    }
    else {
      $field_storage = FieldStorageConfig::loadByName('node', $this->fieldName);
      $this->assertIdentical($field_storage->getSetting('allowed_values'), $result, $message);
    }
  }

  /**
   * Tests normal and key formatter display on node display.
   */
  public function testNodeDisplay() {
    $this->fieldName = strtolower($this->randomMachineName());
    $this->createOptionsField('list_integer');
    $node = $this->drupalCreateNode(['type' => $this->type]);

    $on = $this->randomMachineName();
    $off = $this->randomMachineName();
    $edit = [
      'settings[allowed_values]' =>
        "1|$on
        0|$off",
    ];

    $this->drupalPostForm($this->adminPath, $edit, t('Save field settings'));
    $this->assertText(format_string('Updated field @field_name field settings.', ['@field_name' => $this->fieldName]), "The 'On' and 'Off' form fields work for boolean fields.");

    // Select a default value.
    $edit = [
      $this->fieldName => '1',
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save'));

    // Check the node page and see if the values are correct.
    $file_formatters = ['list_default', 'list_key'];
    foreach ($file_formatters as $formatter) {
      $edit = [
        "fields[$this->fieldName][type]" => $formatter,
        "fields[$this->fieldName][region]" => 'content',
      ];
      $this->drupalPostForm('admin/structure/types/manage/' . $this->typeName . '/display', $edit, t('Save'));
      $this->drupalGet('node/' . $node->id());

      if ($formatter == 'list_default') {
        $output = $on;
      }
      else {
        $output = '1';
      }

      $elements = $this->xpath('//div[text()="' . $output . '"]');
      $this->assertEqual(count($elements), 1, 'Correct options found.');
    }
  }

}
