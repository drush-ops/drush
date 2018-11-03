<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Base class for testing inline blocks.
 */
abstract class InlineBlockTestBase extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * Locator for inline blocks.
   */
  const INLINE_BLOCK_LOCATOR = '.block-inline-blockbasic';

  /**
   * Path prefix for the field UI for the test bundle.
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block_content',
    'layout_builder',
    'block',
    'node',
    'contextual',
    // @todo Remove after https://www.drupal.org/project/drupal/issues/2901792.
    'no_transitions_css',
  ];

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'bundle_with_section_field', 'new_revision' => TRUE]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node2 title',
      'body' => [
        [
          'value' => 'The node2 body',
        ],
      ],
    ]);
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
      'revision' => 1,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());

    $this->blockStorage = $this->container->get('entity_type.manager')->getStorage('block_content');
  }

  /**
   * Saves a layout and asserts the message is correct.
   */
  protected function assertSaveLayout() {
    $assert_session = $this->assertSession();
    $assert_session->linkExists('Save Layout');
    // Go to the Save Layout page. Currently there are random test failures if
    // 'clickLink()' is used.
    // @todo Convert tests that extend this class to NightWatch tests in
    // https://www.drupal.org/node/2984161
    $link = $this->getSession()->getPage()->findLink('Save Layout');
    $this->drupalGet($link->getAttribute('href'));
    $this->assertNotEmpty($assert_session->waitForElement('css', '.messages--status'));

    if (stristr($this->getUrl(), 'admin/structure') === FALSE) {
      $assert_session->pageTextContains('The layout override has been saved.');
    }
    else {
      $assert_session->pageTextContains('The layout has been saved.');
    }
  }

  /**
   * Gets the latest block entity id.
   */
  protected function getLatestBlockEntityId() {
    $block_ids = \Drupal::entityQuery('block_content')->sort('id', 'DESC')->range(0, 1)->execute();
    $block_id = array_pop($block_ids);
    $this->assertNotEmpty($this->blockStorage->load($block_id));
    return $block_id;
  }

  /**
   * Removes an entity block from the layout but does not save the layout.
   */
  protected function removeInlineBlockFromLayout() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $block_text = $page->find('css', static::INLINE_BLOCK_LOCATOR)->getText();
    $this->assertNotEmpty($block_text);
    $assert_session->pageTextContains($block_text);
    $this->clickContextualLink(static::INLINE_BLOCK_LOCATOR, 'Remove block');
    $assert_session->waitForElement('css', "#drupal-off-canvas input[value='Remove']");
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', '#drupal-off-canvas')->pressButton('Remove');
    $this->waitForNoElement('#drupal-off-canvas');
    $this->waitForNoElement(static::INLINE_BLOCK_LOCATOR);
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains($block_text);
  }

  /**
   * Adds an entity block to the layout.
   *
   * @param string $title
   *   The title field value.
   * @param string $body
   *   The body field value.
   */
  protected function addInlineBlockToLayout($title, $body) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->clickLink('Add Block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '.block-categories details:contains(Create new block)'));
    $this->clickLink('Basic block');
    $assert_session->assertWaitOnAjaxRequest();
    $textarea = $assert_session->waitForElement('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $assert_session->fieldValueEquals('Title', '');
    $page->findField('Title')->setValue($title);
    $textarea->setValue($body);
    $page->pressButton('Add Block');
    $this->assertDialogClosedAndTextVisible($body, static::INLINE_BLOCK_LOCATOR);
  }

  /**
   * Configures an inline block in the Layout Builder.
   *
   * @param string $old_body
   *   The old body field value.
   * @param string $new_body
   *   The new body field value.
   * @param string $block_css_locator
   *   The CSS locator to use to select the contextual link.
   */
  protected function configureInlineBlock($old_body, $new_body, $block_css_locator = NULL) {
    $block_css_locator = $block_css_locator ?: static::INLINE_BLOCK_LOCATOR;
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->clickContextualLink($block_css_locator, 'Configure');
    $textarea = $assert_session->waitForElementVisible('css', '[name="settings[block_form][body][0][value]"]');
    $this->assertNotEmpty($textarea);
    $this->assertSame($old_body, $textarea->getValue());
    $textarea->setValue($new_body);
    $page->pressButton('Update');
    $this->waitForNoElement('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertDialogClosedAndTextVisible($new_body);
  }

  /**
   * Waits for an element to be removed from the page.
   *
   * @param string $selector
   *   CSS selector.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   *
   * @todo Remove in https://www.drupal.org/node/2892440.
   */
  protected function waitForNoElement($selector, $timeout = 10000) {
    $condition = "(typeof jQuery !== 'undefined' && jQuery('$selector').length === 0)";
    $this->assertJsCondition($condition, $timeout);
  }

  /**
   * Asserts that the dialog closes and the new text appears on the main canvas.
   *
   * @param string $text
   *   The text.
   * @param string|null $css_locator
   *   The css locator to use inside the main canvas if any.
   */
  protected function assertDialogClosedAndTextVisible($text, $css_locator = NULL) {
    $assert_session = $this->assertSession();
    $this->waitForNoElement('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', '#drupal-off-canvas');
    if ($css_locator) {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".dialog-off-canvas-main-canvas $css_locator:contains('$text')"));
    }
    else {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', ".dialog-off-canvas-main-canvas:contains('$text')"));
    }
  }

}
