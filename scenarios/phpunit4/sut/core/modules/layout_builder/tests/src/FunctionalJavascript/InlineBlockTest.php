<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\node\Entity\Node;

/**
 * Tests that the inline block feature works correctly.
 *
 * @group layout_builder
 */
class InlineBlockTest extends InlineBlockTestBase {

  /**
   * Tests adding and editing of inline blocks.
   */
  public function testInlineBlocks() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));

    // Enable layout builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
    $this->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display-layout/default');
    // Add a basic block with the body field set.
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Enable overrides.
    $this->drupalPostForm(static::FIELD_UI_PREFIX . '/display/default', ['layout[allow_custom]' => TRUE], 'Save');
    $this->drupalGet('node/1/layout');

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('The DEFAULT block body', 'The NEW block body!');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body');
    $assert_session->pageTextNotContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');

    // Add a basic block with the body field set.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('2nd Block title', 'The 2nd block body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body!');
    $assert_session->pageTextContains('The 2nd block body');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');
    $assert_session->pageTextNotContains('The 2nd block body');

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    /* @var \Behat\Mink\Element\NodeElement $inline_block_2 */
    $inline_block_2 = $page->findAll('css', static::INLINE_BLOCK_LOCATOR)[1];
    $uuid = $inline_block_2->getAttribute('data-layout-block-uuid');
    $block_css_locator = static::INLINE_BLOCK_LOCATOR . "[data-layout-block-uuid=\"$uuid\"]";
    $this->configureInlineBlock('The 2nd block body', 'The 2nd NEW block body!', $block_css_locator);
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body!');
    $assert_session->pageTextContains('The 2nd NEW block body!');
    $this->drupalGet('node/2');
    // Node 2 should use default layout.
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body!');
    $assert_session->pageTextNotContains('The 2nd NEW block body!');

    // The default layout entity block should be changed.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display-layout/default');
    $assert_session->pageTextContains('The DEFAULT block body');
    // Confirm default layout still only has 1 entity block.
    $assert_session->elementsCount('css', static::INLINE_BLOCK_LOCATOR, 1);
  }

  /**
   * Tests adding a new entity block and then not saving the layout.
   *
   * @dataProvider layoutNoSaveProvider
   */
  public function testNoLayoutSave($operation, $no_save_link_text, $confirm_button_text) {

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
    ]));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->assertEmpty($this->blockStorage->loadMultiple(), 'No entity blocks exist');
    // Enable layout builder and overrides.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE, 'layout[allow_custom]' => TRUE],
      'Save'
    );

    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block title', 'The block body');
    $this->clickLink($no_save_link_text);
    if ($confirm_button_text) {
      $page->pressButton($confirm_button_text);
    }
    $this->drupalGet('node/1');
    $this->assertEmpty($this->blockStorage->loadMultiple(), 'No entity blocks were created when layout is canceled.');
    $assert_session->pageTextNotContains('The block body');

    $this->drupalGet('node/1/layout');

    $this->addInlineBlockToLayout('Block title', 'The block body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The block body');
    $blocks = $this->blockStorage->loadMultiple();
    $this->assertEquals(count($blocks), 1);
    /* @var \Drupal\Core\Entity\ContentEntityBase $block */
    $block = array_pop($blocks);
    $revision_id = $block->getRevisionId();

    // Confirm the block can be edited.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('The block body', 'The block updated body');

    $this->clickLink($no_save_link_text);
    if ($confirm_button_text) {
      $page->pressButton($confirm_button_text);
    }
    $this->drupalGet('node/1');

    $blocks = $this->blockStorage->loadMultiple();
    // When reverting or canceling the update block should not be on the page.
    $assert_session->pageTextNotContains('The block updated body');
    if ($operation === 'cancel') {
      // When canceling the original block body should appear.
      $assert_session->pageTextContains('The block body');

      $this->assertEquals(count($blocks), 1);
      $block = array_pop($blocks);
      $this->assertEquals($block->getRevisionId(), $revision_id);
      $this->assertEquals($block->get('body')->getValue()[0]['value'], 'The block body');
    }
    else {
      // The block should not be visible.
      // Blocks are currently only deleted when the parent entity is deleted.
      $assert_session->pageTextNotContains('The block body');
    }
  }

  /**
   * Provides test data for ::testNoLayoutSave().
   */
  public function layoutNoSaveProvider() {
    return [
      'cancel' => [
        'cancel',
        'Cancel Layout',
        NULL,
      ],
      'revert' => [
        'revert',
        'Revert to defaults',
        'Revert',
      ],
    ];
  }

  /**
   * Tests entity blocks revisioning.
   */
  public function testInlineBlocksRevisioning() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'administer nodes',
      'bypass node access',
    ]));
    // Enable layout builder and overrides.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE, 'layout[allow_custom]' => TRUE],
      'Save'
    );
    $this->drupalGet('node/1/layout');

    // Add an inline block.
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');

    $assert_session->pageTextContains('The DEFAULT block body');

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $original_revision_id = $node_storage->getLatestRevisionId(1);

    // Create a new revision.
    $this->drupalGet('node/1/edit');
    $page->findField('title[0][value]')->setValue('Node updated');
    $page->pressButton('Save');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');

    $assert_session->linkExists('Revisions');

    // Update the block.
    $this->drupalGet('node/1/layout');
    $this->configureInlineBlock('The DEFAULT block body', 'The NEW block body');
    $this->assertSaveLayout();
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The NEW block body');
    $assert_session->pageTextNotContains('The DEFAULT block body');

    $revision_url = "node/1/revisions/$original_revision_id";

    // Ensure viewing the previous revision shows the previous block revision.
    $this->drupalGet("$revision_url/view");
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');

    // Revert to first revision.
    $revision_url = "$revision_url/revert";
    $this->drupalGet($revision_url);
    $page->pressButton('Revert');

    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $assert_session->pageTextNotContains('The NEW block body');
  }

  /**
   * Tests that entity blocks deleted correctly.
   */
  public function testDeletion() {
    /** @var \Drupal\Core\Cron $cron */
    $cron = \Drupal::service('cron');
    /** @var \Drupal\layout_builder\InlineBlockUsage $usage */
    $usage = \Drupal::service('inline_block.usage');
    $this->drupalLogin($this->drupalCreateUser([
      'administer content types',
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'administer nodes',
      'bypass node access',
    ]));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Enable layout builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
    // Add a block to default layout.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display-layout/default');
    $this->addInlineBlockToLayout('Block title', 'The DEFAULT block body');
    $this->assertSaveLayout();

    $this->assertCount(1, $this->blockStorage->loadMultiple());
    $default_block_id = $this->getLatestBlockEntityId();

    // Ensure the block shows up on node pages.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('The DEFAULT block body');
    $this->drupalGet('node/2');
    $assert_session->pageTextContains('The DEFAULT block body');

    // Enable overrides.
    $this->drupalPostForm(static::FIELD_UI_PREFIX . '/display/default', ['layout[allow_custom]' => TRUE], 'Save');

    // Ensure we have 2 copies of the block in node overrides.
    $this->drupalGet('node/1/layout');
    $this->assertSaveLayout();
    $node_1_block_id = $this->getLatestBlockEntityId();

    $this->drupalGet('node/2/layout');
    $this->assertSaveLayout();
    $node_2_block_id = $this->getLatestBlockEntityId();
    $this->assertCount(3, $this->blockStorage->loadMultiple());

    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display-layout/default');

    $this->assertNotEmpty($this->blockStorage->load($default_block_id));
    $this->assertNotEmpty($usage->getUsage($default_block_id));
    // Remove block from default.
    $this->removeInlineBlockFromLayout();
    $this->assertSaveLayout();
    // Ensure the block in the default was deleted.
    $this->blockStorage->resetCache([$default_block_id]);
    $this->assertEmpty($this->blockStorage->load($default_block_id));
    // Ensure other blocks still exist.
    $this->assertCount(2, $this->blockStorage->loadMultiple());
    $this->assertEmpty($usage->getUsage($default_block_id));

    $this->drupalGet('node/1/layout');
    $assert_session->pageTextContains('The DEFAULT block body');

    $this->removeInlineBlockFromLayout();
    $this->assertSaveLayout();
    $cron->run();
    // Ensure entity block is not deleted because it is needed in revision.
    $this->assertNotEmpty($this->blockStorage->load($node_1_block_id));
    $this->assertCount(2, $this->blockStorage->loadMultiple());

    $this->assertNotEmpty($usage->getUsage($node_1_block_id));
    // Ensure entity block is deleted when node is deleted.
    $this->drupalGet('node/1/delete');
    $page->pressButton('Delete');
    $this->assertEmpty(Node::load(1));
    $cron->run();
    $this->assertEmpty($this->blockStorage->load($node_1_block_id));
    $this->assertEmpty($usage->getUsage($node_1_block_id));
    $this->assertCount(1, $this->blockStorage->loadMultiple());

    // Add another block to the default.
    $this->drupalGet(static::FIELD_UI_PREFIX . '/display/default');
    $this->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display-layout/default');
    $this->addInlineBlockToLayout('Title 2', 'Body 2');
    $this->assertSaveLayout();
    $cron->run();
    $default_block2_id = $this->getLatestBlockEntityId();
    $this->assertCount(2, $this->blockStorage->loadMultiple());

    // Delete the other node so bundle can be deleted.
    $this->assertNotEmpty($usage->getUsage($node_2_block_id));
    $this->drupalGet('node/2/delete');
    $page->pressButton('Delete');
    $this->assertEmpty(Node::load(2));
    $cron->run();
    // Ensure entity block was deleted.
    $this->assertEmpty($this->blockStorage->load($node_2_block_id));
    $this->assertEmpty($usage->getUsage($node_2_block_id));
    $this->assertCount(1, $this->blockStorage->loadMultiple());

    // Delete the bundle which has the default layout.
    $this->assertNotEmpty($usage->getUsage($default_block2_id));
    $this->drupalGet(static::FIELD_UI_PREFIX . '/delete');
    $page->pressButton('Delete');
    $cron->run();

    // Ensure the entity block in default is deleted when bundle is deleted.
    $this->assertEmpty($this->blockStorage->load($default_block2_id));
    $this->assertEmpty($usage->getUsage($default_block2_id));
    $this->assertCount(0, $this->blockStorage->loadMultiple());
  }

  /**
   * Tests access to the block edit form of inline blocks.
   *
   * This module does not provide links to these forms but in case the paths are
   * accessed directly they should accessible by users with the
   * 'configure any layout' permission.
   *
   * @see layout_builder_block_content_access()
   */
  public function testAccess() {
    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
      'administer node fields',
    ]));
    $assert_session = $this->assertSession();

    // Enable layout builder and overrides.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE, 'layout[allow_custom]' => TRUE],
      'Save'
    );

    // Ensure we have 2 copies of the block in node overrides.
    $this->drupalGet('node/1/layout');
    $this->addInlineBlockToLayout('Block title', 'Block body');
    $this->assertSaveLayout();
    $node_1_block_id = $this->getLatestBlockEntityId();

    $this->drupalGet("block/$node_1_block_id");
    $assert_session->pageTextNotContains('You are not authorized to access this page');

    $this->drupalLogout();
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
    ]));

    $this->drupalGet("block/$node_1_block_id");
    $assert_session->pageTextContains('You are not authorized to access this page');

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
    ]));
    $this->drupalGet("block/$node_1_block_id");
    $assert_session->pageTextNotContains('You are not authorized to access this page');
  }

}
