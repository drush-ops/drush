<?php

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\node\Entity\Node;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Test the content moderation actions.
 *
 * @group content_moderation
 */
class ModerationActionsTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use ContentModerationTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'content_moderation',
    'node',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $moderated_bundle = $this->createContentType(['type' => 'moderated_bundle']);
    $moderated_bundle->save();
    $standard_bundle = $this->createContentType(['type' => 'standard_bundle']);
    $standard_bundle->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'moderated_bundle');
    $workflow->save();

    $admin = $this->drupalCreateUser([
      'access content overview',
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($admin);
  }

  /**
   * Test the node status actions report moderation status to users correctly.
   *
   * @dataProvider nodeStatusActionsTestCases
   */
  public function testNodeStatusActions($action, $bundle, $warning_appears, $starting_status, $final_status) {
    // Create and run an action on a node.
    $node = Node::create([
      'type' => $bundle,
      'title' => $this->randomString(),
      'status' => $starting_status,
    ]);
    if ($bundle == 'moderated_bundle') {
      $node->moderation_state->value = $starting_status ? 'published' : 'draft';
    }
    $node->save();

    $this->drupalPostForm('admin/content', [
      'node_bulk_form[0]' => TRUE,
      'action' => $action,
    ], 'Apply to selected items');

    if ($warning_appears) {
      if ($action == 'node_publish_action') {
        $this->assertSession()
          ->elementContains('css', '.messages--warning', node_get_type_label($node) . ' content items were skipped as they are under moderation and may not be directly published.');
      }
      else {
        $this->assertSession()
          ->elementContains('css', '.messages--warning', node_get_type_label($node) . ' content items were skipped as they are under moderation and may not be directly unpublished.');
      }
    }
    else {
      $this->assertSession()->elementNotExists('css', '.messages--warning');
    }

    // Ensure after the action has run, the node matches the expected status.
    $node = Node::load($node->id());
    $this->assertEquals($node->isPublished(), $final_status);
  }

  /**
   * Test cases for ::testNodeStatusActions.
   *
   * @return array
   *   An array of test cases.
   */
  public function nodeStatusActionsTestCases() {
    return [
      'Moderated bundle shows warning (publish action)' => [
        'node_publish_action',
        'moderated_bundle',
        TRUE,
        // If the node starts out unpublished, the action should not work.
        FALSE,
        FALSE,
      ],
      'Moderated bundle shows warning (unpublish action)' => [
        'node_unpublish_action',
        'moderated_bundle',
        TRUE,
        // If the node starts out published, the action should not work.
        TRUE,
        TRUE,
      ],
      'Normal bundle works (publish action)' => [
        'node_publish_action',
        'standard_bundle',
        FALSE,
        // If the node starts out unpublished, the action should work.
        FALSE,
        TRUE,
      ],
      'Normal bundle works (unpublish action)' => [
        'node_unpublish_action',
        'standard_bundle',
        FALSE,
        // If the node starts out published, the action should work.
        TRUE,
        FALSE,
      ],
    ];
  }

}
