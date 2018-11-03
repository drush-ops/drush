<?php

namespace Drupal\Tests\comment\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests comment status field access.
 *
 * @group comment
 */
class CommentStatusFieldAccessTest extends BrowserTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  public $profile = 'testing';

  /**
   * Comment admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $commentAdmin;

  /**
   * Node author.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $nodeAuthor;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'comment',
    'user',
    'system',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => t('Article'),
    ]);
    $node_type->save();
    $this->nodeAuthor = $this->drupalCreateUser([
      'create article content',
      'skip comment approval',
      'post comments',
      'edit own comments',
      'access comments',
      'administer nodes',
    ]);
    $this->commentAdmin = $this->drupalCreateUser([
      'administer comments',
      'create article content',
      'edit own comments',
      'skip comment approval',
      'post comments',
      'access comments',
      'administer nodes',
    ]);
    $this->addDefaultCommentField('node', 'article');
  }

  /**
   * Tests comment status field access.
   */
  public function testCommentStatusFieldAccessStatus() {
    $this->drupalLogin($this->nodeAuthor);
    $this->drupalGet('node/add/article');
    $assert = $this->assertSession();
    $assert->fieldNotExists('comment[0][status]');
    $this->submitForm([
      'title[0][value]' => 'Node 1',
    ], t('Save'));
    $assert->fieldExists('subject[0][value]');
    $this->drupalLogin($this->commentAdmin);
    $this->drupalGet('node/add/article');
    $assert->fieldExists('comment[0][status]');
    $this->submitForm([
      'title[0][value]' => 'Node 2',
    ], t('Save'));
    $assert->fieldExists('subject[0][value]');
  }

}
