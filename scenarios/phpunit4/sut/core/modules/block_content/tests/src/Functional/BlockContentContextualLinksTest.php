<?php

namespace Drupal\Tests\block_content\Functional;

/**
 * Tests views contextual links on block content.
 *
 * @group block_content
 */
class BlockContentContextualLinksTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'contextual',
  ];

  /**
   * Tests contextual links.
   */
  public function testBlockContentContextualLinks() {
    $block_content = $this->createBlockContent();

    $block = $this->placeBlock('block_content:' . $block_content->uuid());

    $user = $this->drupalCreateUser([
      'administer blocks',
      'access contextual links',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('<front>');
    $this->assertSession()->elementAttributeContains('css', 'div[data-contextual-id]', 'data-contextual-id', 'block:block=' . $block->id() . ':langcode=en|block_content:block_content=' . $block_content->id() . ':');
  }

}
