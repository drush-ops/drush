<?php

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests node validation constraints.
 *
 * @group node
 */
class NodeValidationTest extends EntityKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node'];

  /**
   * Set the default field storage backend for fields created during tests.
   */
  protected function setUp() {
    parent::setUp();

    // Create a node type for testing.
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();
  }

  /**
   * Tests the node validation constraints.
   */
  public function testValidation() {
    $this->createUser();
    $node = Node::create(['type' => 'page', 'title' => 'test', 'uid' => 1]);
    $violations = $node->validate();
    $this->assertEqual(count($violations), 0, 'No violations when validating a default node.');

    $node->set('title', $this->randomString(256));
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when title is too long.');
    $this->assertEqual($violations[0]->getPropertyPath(), 'title.0.value');
    $this->assertEqual($violations[0]->getMessage(), '<em class="placeholder">Title</em>: may not be longer than 255 characters.');

    $node->set('title', NULL);
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when title is not set.');
    $this->assertEqual($violations[0]->getPropertyPath(), 'title');
    $this->assertEqual($violations[0]->getMessage(), 'This value should not be null.');

    $node->set('title', '');
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when title is set to an empty string.');
    $this->assertEqual($violations[0]->getPropertyPath(), 'title');

    // Make the title valid again.
    $node->set('title', $this->randomString());
    // Save the node so that it gets an ID and a changed date.
    $node->save();
    // Set the changed date to something in the far past.
    $node->set('changed', 433918800);
    $violations = $node->validate();
    $this->assertEqual(count($violations), 1, 'Violation found when changed date is before the last changed date.');
    $this->assertEqual($violations[0]->getPropertyPath(), '');
    $this->assertEqual($violations[0]->getMessage(), 'The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.');
  }

}
