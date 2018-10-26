<?php

namespace Drupal\devel_generate\Tests;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\Language;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the logic to generate data.
 *
 * @group devel_generate
 */
class DevelGenerateTest extends WebTestBase {

  use CommentTestTrait;
  use EntityReferenceTestTrait;

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('menu_ui', 'node', 'comment', 'taxonomy', 'path', 'devel_generate');

  /**
   * Prepares the testing environment
   */
  public function setUp() {
    parent::setUp();

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic Page'));
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
      $this->addDefaultCommentField('node', 'article');
    }

    // Creating a vocabulary to associate taxonomy terms generated.
    $this->vocabulary = entity_create('taxonomy_vocabulary', array(
      'name' => $this->randomMachineName(),
      'description' => $this->randomMachineName(),
      'vid' => Unicode::strtolower($this->randomMachineName()),
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
      'weight' => mt_rand(0, 10),
    ));
    $this->vocabulary->save();

    // Creates a field of an entity reference field storage on article.
    $field_name = 'taxonomy_' . $this->vocabulary->id();

    $handler_settings = array(
      'target_bundles' => array(
        $this->vocabulary->id() => $this->vocabulary->id(),
      ),
      'auto_create' => TRUE,
    );
    $this->createEntityReferenceField('node', 'article', $field_name, NULL, 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    entity_get_form_display('node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'options_select',
      ))
      ->save();

    entity_get_display('node', 'article', 'default')
      ->setComponent($field_name, array(
        'type' => 'entity_reference_label',
      ))
      ->save();

    $admin_user = $this->drupalCreateUser(array('administer devel_generate'));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests generate commands
   */
  public function testDevelGenerate() {
    // Creating users.
    $edit = array(
      'num' => 4,
    );
    $this->drupalPostForm('admin/config/development/generate/user', $edit, t('Generate'));
    $this->assertText(t('4 users created.'));
    $this->assertText(t('Generate process complete.'));

    // Tests that if no content types are selected an error message is shown.
    $edit = array(
      'num' => 4,
      'title_length' => 4,
    );
    $this->drupalPostForm('admin/config/development/generate/content', $edit, t('Generate'));
    $this->assertText(t('Please select at least one content type'));

    // Creating content.
    // First we create a node in order to test the Delete content checkbox.
    $this->drupalCreateNode(array('type' => 'article'));

    $edit = array(
      'num' => 4,
      'kill' => TRUE,
      'node_types[article]' => TRUE,
      'time_range' => 604800,
      'max_comments' => 3,
      'title_length' => 4,
      'add_alias' => 1,
    );
    $this->drupalPostForm('admin/config/development/generate/content', $edit, t('Generate'));
    $this->assertText(t('Deleted 1 nodes.'));
    $this->assertText(t('Finished creating 4 nodes'));
    $this->assertText(t('Generate process complete.'));

    // Tests that nodes have been created in the generation process.
    $nodes = Node::loadMultiple();
    $this->assert(count($nodes) == 4, 'Nodes generated successfully.');

    // Tests url alias for the generated nodes.
    foreach ($nodes as $node) {
      $alias = 'node-' . $node->id() . '-' . $node->bundle();
      $this->drupalGet($alias);
      $this->assertResponse('200');
      $this->assertText($node->getTitle(), 'Generated url alias for the node works.');
    }

    // Creating terms.
    $edit = array(
      'vids[]' => $this->vocabulary->id(),
      'num' => 5,
      'title_length' => 12,
    );
    $this->drupalPostForm('admin/config/development/generate/term', $edit, t('Generate'));
    $this->assertText(t('Created the following new terms: '));
    $this->assertText(t('Generate process complete.'));

    // Creating vocabularies.
    $edit = array(
      'num' => 5,
      'title_length' => 12,
      'kill' => TRUE,
    );
    $this->drupalPostForm('admin/config/development/generate/vocabs', $edit, t('Generate'));
    $this->assertText(t('Created the following new vocabularies: '));
    $this->assertText(t('Generate process complete.'));

    // Creating menus.
    $edit = array(
      'num_menus' => 5,
      'num_links' => 7,
      'title_length' => 12,
      'link_types[node]' => 1,
      'link_types[front]' => 1,
      'link_types[external]' => 1,
      'max_depth' => 4,
      'max_width' => 6,
      'kill' => 1,
    );
    $this->drupalPostForm('admin/config/development/generate/menu', $edit, t('Generate'));
    $this->assertText(t('Created the following new menus: '));
    $this->assertText(t('Created 7 new menu links'));
    $this->assertText(t('Generate process complete.'));
  }

}
