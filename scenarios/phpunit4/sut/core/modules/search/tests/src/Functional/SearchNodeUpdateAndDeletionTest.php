<?php

namespace Drupal\Tests\search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests search index is updated properly when nodes are removed or updated.
 *
 * @group search
 */
class SearchNodeUpdateAndDeletionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'search'];

  /**
   * A user with permission to access and search content.
   *
   * @var \Drupal\user\UserInterface
   */
  public $testUser;

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    // Create a test user and log in.
    $this->testUser = $this->drupalCreateUser(['access content', 'search content']);
    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests that the search index info is properly updated when a node changes.
   */
  public function testSearchIndexUpdateOnNodeChange() {
    // Create a node.
    $node = $this->drupalCreateNode([
      'title' => 'Someone who says Ni!',
      'body' => [['value' => "We are the knights who say Ni!"]],
      'type' => 'page',
    ]);

    $node_search_plugin = $this->container->get('plugin.manager.search')->createInstance('node_search');
    // Update the search index.
    $node_search_plugin->updateIndex();
    search_update_totals();

    // Search the node to verify it appears in search results
    $edit = ['keys' => 'knights'];
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertText($node->label());

    // Update the node
    $node->body->value = "We want a shrubbery!";
    $node->save();

    // Run indexer again
    $node_search_plugin->updateIndex();
    search_update_totals();

    // Search again to verify the new text appears in test results.
    $edit = ['keys' => 'shrubbery'];
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertText($node->label());
  }

  /**
   * Tests that the search index info is updated when a node is deleted.
   */
  public function testSearchIndexUpdateOnNodeDeletion() {
    // Create a node.
    $node = $this->drupalCreateNode([
      'title' => 'No dragons here',
      'body' => [['value' => 'Again: No dragons here']],
      'type' => 'page',
    ]);

    $node_search_plugin = $this->container->get('plugin.manager.search')->createInstance('node_search');
    // Update the search index.
    $node_search_plugin->updateIndex();
    search_update_totals();

    // Search the node to verify it appears in search results
    $edit = ['keys' => 'dragons'];
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertText($node->label());

    // Get the node info from the search index tables.
    $search_index_dataset = db_query("SELECT sid FROM {search_index} WHERE type = 'node_search' AND  word = :word", [':word' => 'dragons'])
      ->fetchField();
    $this->assertNotEqual($search_index_dataset, FALSE, t('Node info found on the search_index'));

    // Delete the node.
    $node->delete();

    // Check if the node info is gone from the search table.
    $search_index_dataset = db_query("SELECT sid FROM {search_index} WHERE type = 'node_search' AND  word = :word", [':word' => 'dragons'])
      ->fetchField();
    $this->assertFalse($search_index_dataset, t('Node info successfully removed from search_index'));

    // Search again to verify the node doesn't appear anymore.
    $this->drupalPostForm('search/node', $edit, t('Search'));
    $this->assertNoText($node->label());
  }

}
