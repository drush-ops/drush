<?php

namespace Drupal\Tests\node\Functional;

use Drupal\Component\Utility\Html;

/**
 * Create a node with dangerous tags in its title and test that they are
 * escaped.
 *
 * @group node
 */
class NodeTitleXSSTest extends NodeTestBase {

  /**
   * Tests XSS functionality with a node entity.
   */
  public function testNodeTitleXSS() {
    // Prepare a user to do the stuff.
    $web_user = $this->drupalCreateUser(['create page content', 'edit any page content']);
    $this->drupalLogin($web_user);

    $xss = '<script>alert("xss")</script>';
    $title = $xss . $this->randomMachineName();
    $edit = [];
    $edit['title[0][value]'] = $title;

    $this->drupalPostForm('node/add/page', $edit, t('Preview'));
    $this->assertNoRaw($xss, 'Harmful tags are escaped when previewing a node.');

    $settings = ['title' => $title];
    $node = $this->drupalCreateNode($settings);

    $this->drupalGet('node/' . $node->id());
    // Titles should be escaped.
    $this->assertRaw('<title>' . Html::escape($title) . ' | Drupal</title>', 'Title is displayed when viewing a node.');
    $this->assertNoRaw($xss, 'Harmful tags are escaped when viewing a node.');

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertNoRaw($xss, 'Harmful tags are escaped when editing a node.');
  }

}
