<?php

namespace Drupal\Tests\node\Kernel\Migrate\d7;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Tests\file\Kernel\Migrate\d7\FileMigrationSetupTrait;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests node migration.
 *
 * @group node
 */
class MigrateNodeTest extends MigrateDrupal7TestBase {

  use FileMigrationSetupTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_translation',
    'comment',
    'datetime',
    'file',
    'filter',
    'forum',
    'image',
    'language',
    'link',
    'menu_ui',
    // Required for translation migrations.
    'migrate_drupal_multilingual',
    'node',
    'taxonomy',
    'telephone',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->fileMigrationSetup();

    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(static::$modules);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('forum', ['forum', 'forum_index']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);

    $this->executeMigrations([
      'language',
      'd7_user_role',
      'd7_user',
      'd7_node_type',
      'd7_language_content_settings',
      'd7_comment_type',
      'd7_comment_field',
      'd7_comment_field_instance',
      'd7_taxonomy_vocabulary',
      'd7_field',
      'd7_field_instance',
      'd7_node',
      'd7_node_translation',
      'd7_node_entity_translation',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFileMigrationInfo() {
    return [
      'path' => 'public://sites/default/files/cube.jpeg',
      'size' => '3620',
      'base_path' => 'public://',
      'plugin_id' => 'd7_file',
    ];
  }

  /**
   * Asserts various aspects of a node.
   *
   * @param string $id
   *   The node ID.
   * @param string $type
   *   The node type.
   * @param string $langcode
   *   The expected language code.
   * @param string $title
   *   The expected title.
   * @param int $uid
   *   The expected author ID.
   * @param bool $status
   *   The expected status of the node.
   * @param int $created
   *   The expected creation time.
   * @param int $changed
   *   The expected modification time.
   * @param bool $promoted
   *   Whether the node is expected to be promoted to the front page.
   * @param bool $sticky
   *   Whether the node is expected to be sticky.
   */
  protected function assertEntity($id, $type, $langcode, $title, $uid, $status, $created, $changed, $promoted, $sticky) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load($id);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertEquals($type, $node->getType());
    $this->assertEquals($langcode, $node->langcode->value);
    $this->assertEquals($title, $node->getTitle());
    $this->assertEquals($uid, $node->getOwnerId());
    $this->assertEquals($status, $node->isPublished());
    $this->assertEquals($created, $node->getCreatedTime());
    if (isset($changed)) {
      $this->assertEquals($changed, $node->getChangedTime());
    }
    $this->assertEquals($promoted, $node->isPromoted());
    $this->assertEquals($sticky, $node->isSticky());
  }

  /**
   * Asserts various aspects of a node revision.
   *
   * @param int $id
   *   The revision ID.
   * @param string $title
   *   The expected title.
   * @param int $uid
   *   The revision author ID.
   * @param string $log
   *   The revision log message.
   * @param int $timestamp
   *   The revision's time stamp.
   */
  protected function assertRevision($id, $title, $uid, $log, $timestamp) {
    $revision = \Drupal::entityManager()->getStorage('node')->loadRevision($id);
    $this->assertInstanceOf(NodeInterface::class, $revision);
    $this->assertEquals($title, $revision->getTitle());
    $this->assertEquals($uid, $revision->getRevisionUser()->id());
    $this->assertEquals($log, $revision->revision_log->value);
    $this->assertEquals($timestamp, $revision->getRevisionCreationTime());
  }

  /**
   * Test node migration from Drupal 7 to 8.
   */
  public function testNode() {
    $this->assertEntity(1, 'test_content_type', 'en', 'An English Node', '2', TRUE, '1421727515', '1441032132', TRUE, FALSE);
    $this->assertRevision(1, 'An English Node', '1', NULL, '1441032132');

    $node = Node::load(1);
    $this->assertTrue($node->field_boolean->value);
    $this->assertEquals('99-99-99-99', $node->field_phone->value);
    $this->assertEquals('1', $node->field_float->value);
    $this->assertEquals('5', $node->field_integer->value);
    $this->assertEquals('Some more text', $node->field_text_list[0]->value);
    $this->assertEquals('7', $node->field_integer_list[0]->value);
    $this->assertEquals('qwerty', $node->field_text->value);
    $this->assertEquals('2', $node->field_file->target_id);
    $this->assertEquals('file desc', $node->field_file->description);
    $this->assertTrue($node->field_file->display);
    $this->assertEquals('1', $node->field_images->target_id);
    $this->assertEquals('alt text', $node->field_images->alt);
    $this->assertEquals('title text', $node->field_images->title);
    $this->assertEquals('93', $node->field_images->width);
    $this->assertEquals('93', $node->field_images->height);
    $this->assertEquals('http://google.com', $node->field_link->uri);
    $this->assertEquals('Click Here', $node->field_link->title);
    // Test that an email field is migrated.
    $this->assertEquals('default@example.com', $node->field_email->value);
    $this->assertEquals('another@example.com', $node->field_email[1]->value);
    $this->assertEquals(CommentItemInterface::OPEN, $node->comment_node_test_content_type->status);
    $this->assertEquals('3.1416', $node->field_float_list[0]->value);

    // Test that fields translated with Entity Translation are migrated.
    $node_fr = $node->getTranslation('fr');
    $this->assertEquals('A French Node', $node_fr->getTitle());
    $this->assertEquals('6', $node_fr->field_integer->value);
    $node_is = $node->getTranslation('is');
    $this->assertEquals('An Icelandic Node', $node_is->getTitle());
    $this->assertEquals('7', $node_is->field_integer->value);

    $node = Node::load(2);
    $this->assertEquals('en', $node->langcode->value);
    $this->assertEquals("...is that it's the absolute best show ever. Trust me, I would know.", $node->body->value);
    $this->assertEquals('The thing about Deep Space 9', $node->label());
    $this->assertEquals('internal:/', $node->field_link->uri);
    $this->assertEquals('Home', $node->field_link->title);
    $this->assertEquals(CommentItemInterface::OPEN, $node->comment_node_article->status);
    $this->assertTrue($node->hasTranslation('is'), "Node 2 has an Icelandic translation");

    $translation = $node->getTranslation('is');
    $this->assertEquals('is', $translation->langcode->value);
    $this->assertEquals("is - ...is that it's the absolute best show ever. Trust me, I would know.", $translation->body->value);
    $this->assertEquals('is - The thing about Deep Space 9', $translation->label());
    $this->assertEquals('internal:/', $translation->field_link->uri);
    $this->assertEquals(CommentItemInterface::OPEN, $translation->comment_node_article->status);
    $this->assertEquals('Home', $translation->field_link->title);

    // Test that content_translation_source is set.
    $manager = $this->container->get('content_translation.manager');
    $this->assertEquals('en', $manager->getTranslationMetadata($node->getTranslation('is'))->getSource());

    // Node 3 is a translation of node 2, and should not be imported separately.
    $this->assertNull(Node::load(3), "Node 3 doesn't exist in D8, it was a translation");

    // Test that content_translation_source for a source other than English.
    $node = Node::load(4);
    $this->assertEquals('is', $manager->getTranslationMetadata($node->getTranslation('en'))->getSource());
    $this->assertEquals(CommentItemInterface::CLOSED, $node->comment_node_article->status);

    $translation = $node->getTranslation('en');
    $this->assertEquals(CommentItemInterface::CLOSED, $translation->comment_node_article->status);

    $node = Node::load(6);
    $this->assertEquals(CommentItemInterface::CLOSED, $node->comment_forum->status);

    $node = Node::load(7);
    $this->assertEquals(CommentItemInterface::OPEN, $node->comment_forum->status);
  }

}
