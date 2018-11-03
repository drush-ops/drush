<?php

namespace Drupal\FunctionalTests\Datetime;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the functionality of Timestamp core field UI.
 *
 * @group field
 */
class TimestampTest extends BrowserTestBase {

  /**
   * An array of display options to pass to entity_get_display().
   *
   * @var array
   */
  protected $displayOptions;

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The field used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'entity_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser([
      'access content',
      'view test entity',
      'administer entity_test content',
      'administer entity_test form display',
      'administer content types',
      'administer node fields',
    ]);

    $this->drupalLogin($web_user);
    $field_name = 'field_timestamp';
    $type = 'timestamp';
    $widget_type = 'datetime_timestamp';
    $formatter_type = 'timestamp';

    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => $type,
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'required' => TRUE,
    ]);
    $this->field->save();

    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($field_name, ['type' => $widget_type])
      ->save();

    $this->displayOptions = [
      'type' => $formatter_type,
      'label' => 'hidden',
    ];

    EntityViewDisplay::create([
      'targetEntityType' => $this->field->getTargetEntityTypeId(),
      'bundle' => $this->field->getTargetBundle(),
      'mode' => 'full',
      'status' => TRUE,
    ])->setComponent($field_name, $this->displayOptions)
      ->save();
  }

  /**
   * Tests the "datetime_timestamp" widget.
   */
  public function testWidget() {
    // Build up a date in the UTC timezone.
    $value = '2012-12-31 00:00:00';
    $date = new DrupalDateTime($value, 'UTC');

    // Update the timezone to the system default.
    $date->setTimezone(timezone_open(drupal_get_user_timezone()));

    // Display creation form.
    $this->drupalGet('entity_test/add');

    // Make sure the "datetime_timestamp" widget is on the page.
    $fields = $this->xpath('//div[contains(@class, "field--widget-datetime-timestamp") and @id="edit-field-timestamp-wrapper"]');
    $this->assertEquals(1, count($fields));

    // Look for the widget elements and make sure they are empty.
    $this->assertSession()->fieldExists('field_timestamp[0][value][date]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][date]', '');
    $this->assertSession()->fieldExists('field_timestamp[0][value][time]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][time]', '');

    // Submit the date.
    $date_format = DateFormat::load('html_date')->getPattern();
    $time_format = DateFormat::load('html_time')->getPattern();

    $edit = [
      'field_timestamp[0][value][date]' => $date->format($date_format),
      'field_timestamp[0][value][time]' => $date->format($time_format),
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Make sure the submitted date is set as the default in the widget.
    $this->assertSession()->fieldExists('field_timestamp[0][value][date]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][date]', $date->format($date_format));
    $this->assertSession()->fieldExists('field_timestamp[0][value][time]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][time]', $date->format($time_format));

    // Make sure the entity was saved.
    preg_match('|entity_test/manage/(\d+)|', $this->getSession()->getCurrentUrl(), $match);
    $id = $match[1];
    $this->assertSession()->pageTextContains(sprintf('entity_test %s has been created.', $id));

    // Make sure the timestamp is output properly with the default formatter.
    $medium = DateFormat::load('medium')->getPattern();
    $this->drupalGet('entity_test/' . $id);
    $this->assertSession()->pageTextContains($date->format($medium));
  }

}
