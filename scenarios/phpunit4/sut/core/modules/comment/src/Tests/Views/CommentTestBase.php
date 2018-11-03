<?php

namespace Drupal\comment\Tests\Views;

@trigger_error(__NAMESPACE__ . '\CommentTestBase is deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.0. Use \Drupal\Tests\comment\Functional\Views\CommentTestBase instead. See http://www.drupal.org/node/2908490', E_USER_DEPRECATED);

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\views\Tests\ViewTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\comment\Entity\Comment;

/**
 * Provides setup and helper methods for comment views tests.
 *
 * @deprecated in Drupal 8.4.0 and will be removed before Drupal 9.0.0.
 *   Use \Drupal\Tests\comment\Functional\Views\CommentTestBase instead.
 *
 * @see https://www.drupal.org/node/2908490
 */
abstract class CommentTestBase extends ViewTestBase {

  use CommentTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment', 'comment_test_views'];

  /**
   * A normal user with permission to post comments (without approval).
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * A second normal user that will author a node for $account to comment on.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account2;

  /**
   * Stores a node posted by the user created as $account.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeUserPosted;

  /**
   * Stores a node posted by the user created as $account2.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeUserCommented;

  /**
   * Stores a comment used by the tests.
   *
   * @var \Drupal\comment\Entity\Comment
   */
  protected $comment;

  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(get_class($this), ['comment_test_views']);

    // Add two users, create a node with the user1 as author and another node
    // with user2 as author. For the second node add a comment from user1.
    $this->account = $this->drupalCreateUser(['skip comment approval']);
    $this->account2 = $this->drupalCreateUser();
    $this->drupalLogin($this->account);

    $this->drupalCreateContentType(['type' => 'page', 'name' => t('Basic page')]);
    $this->addDefaultCommentField('node', 'page');

    $this->nodeUserPosted = $this->drupalCreateNode();
    $this->nodeUserCommented = $this->drupalCreateNode(['uid' => $this->account2->id()]);

    $comment = [
      'uid' => $this->loggedInUser->id(),
      'entity_id' => $this->nodeUserCommented->id(),
      'entity_type' => 'node',
      'field_name' => 'comment',
      'subject' => 'How much wood would a woodchuck chuck',
      'cid' => '',
      'pid' => '',
      'mail' => 'someone@example.com',
    ];
    $this->comment = Comment::create($comment);
    $this->comment->save();
  }

}
