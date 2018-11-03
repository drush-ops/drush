<?php

namespace Drupal\Tests\search\Functional;

use Behat\Mink\Exception\ResponseTextException;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\user\RoleInterface;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests integration searching comments.
 *
 * @group search
 */
class SearchCommentTest extends BrowserTestBase {

  use CommentTestTrait;
  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'node', 'comment', 'search'];

  /**
   * Test subject for comments.
   *
   * @var string
   */
  protected $commentSubject;

  /**
   * ID for the administrator role.
   *
   * @var string
   */
  protected $adminRole;

  /**
   * A user with various administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Test node for searching.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ]);
    $full_html_format->save();

    // Create and log in an administrative user having access to the Full HTML
    // text format.
    $permissions = [
      'administer filters',
      $full_html_format->getPermissionName(),
      'administer permissions',
      'create page content',
      'post comments',
      'skip comment approval',
      'access comments',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);
    // Add a comment field.
    $this->addDefaultCommentField('node', 'article');
  }

  /**
   * Verify that comments are rendered using proper format in search results.
   */
  public function testSearchResultsComment() {
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    // Create basic_html format that escapes all HTML.
    $basic_html_format = FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'weight' => 1,
      'filters' => [
        'filter_html_escape' => ['status' => 1],
      ],
      'roles' => [RoleInterface::AUTHENTICATED_ID],
    ]);
    $basic_html_format->save();

    $comment_body = 'Test comment body';

    // Make preview optional.
    $field = FieldConfig::loadByName('node', 'article', 'comment');
    $field->setSetting('preview', DRUPAL_OPTIONAL);
    $field->save();

    // Allow anonymous users to search content.
    $edit = [
      RoleInterface::ANONYMOUS_ID . '[search content]' => 1,
      RoleInterface::ANONYMOUS_ID . '[access comments]' => 1,
      RoleInterface::ANONYMOUS_ID . '[post comments]' => 1,
    ];
    $this->drupalPostForm('admin/people/permissions', $edit, t('Save permissions'));

    // Create a node.
    $node = $this->drupalCreateNode(['type' => 'article']);
    // Post a comment using 'Full HTML' text format.
    $edit_comment = [];
    $edit_comment['subject[0][value]'] = 'Test comment subject';
    $edit_comment['comment_body[0][value]'] = '<h1>' . $comment_body . '</h1>';
    $full_html_format_id = 'full_html';
    $edit_comment['comment_body[0][format]'] = $full_html_format_id;
    $this->drupalPostForm('comment/reply/node/' . $node->id() . '/comment', $edit_comment, t('Save'));

    // Post a comment with an evil script tag in the comment subject and a
    // script tag nearby a keyword in the comment body. Use the 'FULL HTML' text
    // format so the script tag stored.
    $edit_comment2 = [];
    $edit_comment2['subject[0][value]'] = "<script>alert('subjectkeyword');</script>";
    $edit_comment2['comment_body[0][value]'] = "nearbykeyword<script>alert('somethinggeneric');</script>";
    $edit_comment2['comment_body[0][format]'] = $full_html_format_id;
    $this->drupalPostForm('comment/reply/node/' . $node->id() . '/comment', $edit_comment2, t('Save'));

    // Post a comment with a keyword inside an evil script tag in the comment
    // body. Use the 'FULL HTML' text format so the script tag is stored.
    $edit_comment3 = [];
    $edit_comment3['subject[0][value]'] = 'asubject';
    $edit_comment3['comment_body[0][value]'] = "<script>alert('insidekeyword');</script>";
    $edit_comment3['comment_body[0][format]'] = $full_html_format_id;
    $this->drupalPostForm('comment/reply/node/' . $node->id() . '/comment', $edit_comment3, t('Save'));

    // Invoke search index update.
    $this->drupalLogout();
    $this->cronRun();

    // Search for the comment subject.
    $edit = [
      'keys' => "'" . $edit_comment['subject[0][value]'] . "'",
    ];
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $node_storage->resetCache([$node->id()]);
    $node2 = $node_storage->load($node->id());
    $this->assertText($node2->label(), 'Node found in search results.');
    $this->assertText($edit_comment['subject[0][value]'], 'Comment subject found in search results.');

    // Search for the comment body.
    $edit = [
      'keys' => "'" . $comment_body . "'",
    ];
    $this->drupalPostForm(NULL, $edit, t('Search'));
    $this->assertText($node2->label(), 'Node found in search results.');

    // Verify that comment is rendered using proper format.
    $this->assertText($comment_body, 'Comment body text found in search results.');
    $this->assertNoRaw(t('n/a'), 'HTML in comment body is not hidden.');
    $this->assertNoEscaped($edit_comment['comment_body[0][value]'], 'HTML in comment body is not escaped.');

    // Search for the evil script comment subject.
    $edit = [
      'keys' => 'subjectkeyword',
    ];
    $this->drupalPostForm('search/node', $edit, t('Search'));

    // Verify the evil comment subject is escaped in search results.
    $this->assertRaw('&lt;script&gt;alert(&#039;<strong>subjectkeyword</strong>&#039;);');
    $this->assertNoRaw('<script>');

    // Search for the keyword near the evil script tag in the comment body.
    $edit = [
      'keys' => 'nearbykeyword',
    ];
    $this->drupalPostForm('search/node', $edit, t('Search'));

    // Verify that nearby script tag in the evil comment body is stripped from
    // search results.
    $this->assertRaw('<strong>nearbykeyword</strong>');
    $this->assertNoRaw('<script>');

    // Search for contents inside the evil script tag in the comment body.
    $edit = [
      'keys' => 'insidekeyword',
    ];
    $this->drupalPostForm('search/node', $edit, t('Search'));

    // @todo Verify the actual search results.
    //   https://www.drupal.org/node/2551135

    // Verify there is no script tag in search results.
    $this->assertNoRaw('<script>');

    // Hide comments.
    $this->drupalLogin($this->adminUser);
    $node->set('comment', CommentItemInterface::HIDDEN);
    $node->save();

    // Invoke search index update.
    $this->drupalLogout();
    $this->cronRun();

    // Search for $title.
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertText(t('Your search yielded no results.'));
  }

  /**
   * Verify access rules for comment indexing with different permissions.
   */
  public function testSearchResultsCommentAccess() {
    $comment_body = 'Test comment body';
    $this->commentSubject = 'Test comment subject';
    $roles = $this->adminUser->getRoles(TRUE);
    $this->adminRole = $roles[0];

    // Create a node.
    // Make preview optional.
    $field = FieldConfig::loadByName('node', 'article', 'comment');
    $field->setSetting('preview', DRUPAL_OPTIONAL);
    $field->save();
    $this->node = $this->drupalCreateNode(['type' => 'article']);

    // Post a comment using 'Full HTML' text format.
    $edit_comment = [];
    $edit_comment['subject[0][value]'] = $this->commentSubject;
    $edit_comment['comment_body[0][value]'] = '<h1>' . $comment_body . '</h1>';
    $this->drupalPostForm('comment/reply/node/' . $this->node->id() . '/comment', $edit_comment, t('Save'));

    $this->drupalLogout();
    $this->setRolePermissions(RoleInterface::ANONYMOUS_ID);
    $this->assertCommentAccess(FALSE, 'Anon user has search permission but no access comments permission, comments should not be indexed');

    $this->setRolePermissions(RoleInterface::ANONYMOUS_ID, TRUE);
    $this->assertCommentAccess(TRUE, 'Anon user has search permission and access comments permission, comments should be indexed');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/people/permissions');

    // Disable search access for authenticated user to test admin user.
    $this->setRolePermissions(RoleInterface::AUTHENTICATED_ID, FALSE, FALSE);

    $this->setRolePermissions($this->adminRole);
    $this->assertCommentAccess(FALSE, 'Admin user has search permission but no access comments permission, comments should not be indexed');

    $this->drupalGet('node/' . $this->node->id());
    $this->setRolePermissions($this->adminRole, TRUE);
    $this->assertCommentAccess(TRUE, 'Admin user has search permission and access comments permission, comments should be indexed');

    $this->setRolePermissions(RoleInterface::AUTHENTICATED_ID);
    $this->assertCommentAccess(FALSE, 'Authenticated user has search permission but no access comments permission, comments should not be indexed');

    $this->setRolePermissions(RoleInterface::AUTHENTICATED_ID, TRUE);
    $this->assertCommentAccess(TRUE, 'Authenticated user has search permission and access comments permission, comments should be indexed');

    // Verify that access comments permission is inherited from the
    // authenticated role.
    $this->setRolePermissions(RoleInterface::AUTHENTICATED_ID, TRUE, FALSE);
    $this->setRolePermissions($this->adminRole);
    $this->assertCommentAccess(TRUE, 'Admin user has search permission and no access comments permission, but comments should be indexed because admin user inherits authenticated user\'s permission to access comments');

    // Verify that search content permission is inherited from the authenticated
    // role.
    $this->setRolePermissions(RoleInterface::AUTHENTICATED_ID, TRUE, TRUE);
    $this->setRolePermissions($this->adminRole, TRUE, FALSE);
    $this->assertCommentAccess(TRUE, 'Admin user has access comments permission and no search permission, but comments should be indexed because admin user inherits authenticated user\'s permission to search');
  }

  /**
   * Set permissions for role.
   */
  public function setRolePermissions($rid, $access_comments = FALSE, $search_content = TRUE) {
    $permissions = [
      'access comments' => $access_comments,
      'search content' => $search_content,
    ];
    user_role_change_permissions($rid, $permissions);
  }

  /**
   * Update search index and search for comment.
   */
  public function assertCommentAccess($assume_access, $message) {
    // Invoke search index update.
    search_mark_for_reindex('node_search', $this->node->id());
    $this->cronRun();

    // Search for the comment subject.
    $edit = [
      'keys' => "'" . $this->commentSubject . "'",
    ];
    $this->drupalPostForm('search/node', $edit, t('Search'));

    try {
      if ($assume_access) {
        $this->assertSession()->pageTextContains($this->node->label());
        $this->assertSession()->pageTextContains($this->commentSubject);
      }
      else {
        $this->assertSession()->pageTextContains(t('Your search yielded no results.'));
      }
    }
    catch (ResponseTextException $exception) {
      $this->fail($message);
    }
  }

  /**
   * Verify that 'add new comment' does not appear in search results or index.
   */
  public function testAddNewComment() {
    // Create a node with a short body.
    $settings = [
      'type' => 'article',
      'title' => 'short title',
      'body' => [['value' => 'short body text']],
    ];

    $user = $this->drupalCreateUser([
      'search content',
      'create article content',
      'access content',
      'post comments',
      'access comments',
    ]);
    $this->drupalLogin($user);

    $node = $this->drupalCreateNode($settings);
    // Verify that if you view the node on its own page, 'add new comment'
    // is there.
    $this->drupalGet('node/' . $node->id());
    $this->assertText(t('Add new comment'));

    // Run cron to index this page.
    $this->drupalLogout();
    $this->cronRun();

    // Search for 'comment'. Should be no results.
    $this->drupalLogin($user);
    $this->drupalPostForm('search/node', ['keys' => 'comment'], t('Search'));
    $this->assertText(t('Your search yielded no results'));

    // Search for the node title. Should be found, and 'Add new comment' should
    // not be part of the search snippet.
    $this->drupalPostForm('search/node', ['keys' => 'short'], t('Search'));
    $this->assertText($node->label(), 'Search for keyword worked');
    $this->assertNoText(t('Add new comment'));
  }

}
