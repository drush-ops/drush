<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * @coversDefaultClass \Drupal\layout_builder\Plugin\Block\FieldBlock
 *
 * @group field
 */
class FieldBlockTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'datetime', 'layout_builder', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'user',
      'type' => 'datetime',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'user',
      'label' => 'Date field',
    ]);
    $field->save();

    $user = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $user->field_date = '1978-11-19T05:00:00';
    $user->save();
    $this->drupalLogin($user);
  }

  /**
   * Tests configuring a field block for a user field.
   */
  public function testFieldBlock() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Assert that the field value is not displayed.
    $this->drupalGet('admin');
    $assert_session->pageTextNotContains('Sunday, November 19, 1978 - 16:00');

    $this->drupalGet('admin/structure/block');
    $this->clickLink('Place block');
    $assert_session->assertWaitOnAjaxRequest();

    // Ensure that fields without any formatters are not available.
    $assert_session->pageTextNotContains('Password');
    // Ensure that non-display-configurable fields are not available.
    $assert_session->pageTextNotContains('Initial email');

    $assert_session->pageTextContains('Date field');
    $block_url = 'admin/structure/block/add/field_block%3Auser%3Auser%3Afield_date/classy';
    $assert_session->linkByHrefExists($block_url);

    $this->drupalGet($block_url);
    $page->fillField('region', 'content');

    // Assert the default formatter configuration.
    $assert_session->fieldValueEquals('settings[formatter][type]', 'datetime_default');
    $assert_session->fieldValueEquals('settings[formatter][settings][format_type]', 'medium');

    // Change the formatter.
    $page->selectFieldOption('settings[formatter][type]', 'datetime_time_ago');
    $assert_session->assertWaitOnAjaxRequest();
    // Changing the formatter removes the old settings and introduces new ones.
    $assert_session->fieldNotExists('settings[formatter][settings][format_type]');
    $assert_session->fieldExists('settings[formatter][settings][granularity]');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('The block configuration has been saved.');

    // Configure the block and change the formatter again.
    $this->clickLink('Configure');
    $page->selectFieldOption('settings[formatter][type]', 'datetime_default');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldValueEquals('settings[formatter][settings][format_type]', 'medium');
    $page->selectFieldOption('settings[formatter][settings][format_type]', 'long');

    $page->pressButton('Save block');
    $assert_session->pageTextContains('The block configuration has been saved.');

    // Assert that the field value is updated.
    $this->clickLink('Configure');
    $assert_session->fieldValueEquals('settings[formatter][settings][format_type]', 'long');

    // Assert that the field block is configured as expected.
    $expected = [
      'label' => 'above',
      'type' => 'datetime_default',
      'settings' => [
        'format_type' => 'long',
        'timezone_override' => '',
      ],
      'third_party_settings' => [],
    ];
    $config = $this->container->get('config.factory')->get('block.block.datefield');
    $this->assertEquals($expected, $config->get('settings.formatter'));
    $this->assertEquals(['field.field.user.user.field_date'], $config->get('dependencies.config'));

    // Assert that the block is displaying the user field.
    $this->drupalGet('admin');
    $assert_session->pageTextContains('Sunday, November 19, 1978 - 16:00');
  }

}
