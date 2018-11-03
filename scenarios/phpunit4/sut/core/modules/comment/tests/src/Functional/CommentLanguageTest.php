<?php

namespace Drupal\Tests\comment\Functional;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for comment language.
 *
 * @group comment
 */
class CommentLanguageTest extends BrowserTestBase {

  use CommentTestTrait;

  /**
   * Modules to install.
   *
   * We also use the language_test module here to be able to turn on content
   * language negotiation. Drupal core does not provide a way in itself to do
   * that.
   *
   * @var array
   */
  public static $modules = ['node', 'language', 'language_test', 'comment_test'];

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create and log in user.
    $admin_user = $this->drupalCreateUser(['administer site configuration', 'administer languages', 'access administration pages', 'administer content types', 'administer comments', 'create article content', 'access comments', 'post comments', 'skip comment approval']);
    $this->drupalLogin($admin_user);

    // Add language.
    $edit = ['predefined_langcode' => 'fr'];
    $this->drupalPostForm('admin/config/regional/language/add', $edit, t('Add language'));

    // Set "Article" content type to use multilingual support.
    $edit = ['language_configuration[language_alterable]' => TRUE];
    $this->drupalPostForm('admin/structure/types/manage/article', $edit, t('Save content type'));

    // Enable content language negotiation UI.
    \Drupal::state()->set('language_test.content_language_type', TRUE);

    // Set interface language detection to user and content language detection
    // to URL. Disable inheritance from interface language to ensure content
    // language will fall back to the default language if no URL language can be
    // detected.
    $edit = [
      'language_interface[enabled][language-user]' => TRUE,
      'language_content[enabled][language-url]' => TRUE,
      'language_content[enabled][language-interface]' => FALSE,
    ];
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));

    // Change user language preference, this way interface language is always
    // French no matter what path prefix the URLs have.
    $edit = ['preferred_langcode' => 'fr'];
    $this->drupalPostForm("user/" . $admin_user->id() . "/edit", $edit, t('Save'));

    // Create comment field on article.
    $this->addDefaultCommentField('node', 'article');

    // Make comment body translatable.
    $field_storage = FieldStorageConfig::loadByName('comment', 'comment_body');
    $field_storage->setTranslatable(TRUE);
    $field_storage->save();
    $this->assertTrue($field_storage->isTranslatable(), 'Comment body is translatable.');
  }

  /**
   * Test that comment language is properly set.
   */
  public function testCommentLanguage() {

    // Create two nodes, one for english and one for french, and comment each
    // node using both english and french as content language by changing URL
    // language prefixes. Meanwhile interface language is always French, which
    // is the user language preference. This way we can ensure that node
    // language and interface language do not influence comment language, as
    // only content language has to.
    foreach ($this->container->get('language_manager')->getLanguages() as $node_langcode => $node_language) {
      // Create "Article" content.
      $title = $this->randomMachineName();
      $edit = [
        'title[0][value]' => $title,
        'body[0][value]' => $this->randomMachineName(),
        'langcode[0][value]' => $node_langcode,
        'comment[0][status]' => CommentItemInterface::OPEN,
      ];
      $this->drupalPostForm("node/add/article", $edit, t('Save'));
      $node = $this->drupalGetNodeByTitle($title);

      $prefixes = language_negotiation_url_prefixes();
      foreach ($this->container->get('language_manager')->getLanguages() as $langcode => $language) {
        // Post a comment with content language $langcode.
        $prefix = empty($prefixes[$langcode]) ? '' : $prefixes[$langcode] . '/';
        $comment_values[$node_langcode][$langcode] = $this->randomMachineName();
        $edit = [
          'subject[0][value]' => $this->randomMachineName(),
          'comment_body[0][value]' => $comment_values[$node_langcode][$langcode],
        ];
        $this->drupalPostForm($prefix . 'node/' . $node->id(), $edit, t('Preview'));
        $this->drupalPostForm(NULL, $edit, t('Save'));

        // Check that comment language matches the current content language.
        $cids = \Drupal::entityQuery('comment')
          ->condition('entity_id', $node->id())
          ->condition('entity_type', 'node')
          ->condition('field_name', 'comment')
          ->sort('cid', 'DESC')
          ->range(0, 1)
          ->execute();
        $comment = Comment::load(reset($cids));
        $args = ['%node_language' => $node_langcode, '%comment_language' => $comment->langcode->value, '%langcode' => $langcode];
        $this->assertEqual($comment->langcode->value, $langcode, format_string('The comment posted with content language %langcode and belonging to the node with language %node_language has language %comment_language', $args));
        $this->assertEqual($comment->comment_body->value, $comment_values[$node_langcode][$langcode], 'Comment body correctly stored.');
      }
    }

    // Check that comment bodies appear in the administration UI.
    $this->drupalGet('admin/content/comment');
    foreach ($comment_values as $node_values) {
      foreach ($node_values as $value) {
        $this->assertRaw($value);
      }
    }
  }

}
