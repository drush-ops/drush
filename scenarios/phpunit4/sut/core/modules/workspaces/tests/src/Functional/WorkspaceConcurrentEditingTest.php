<?php

namespace Drupal\Tests\workspaces\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests concurrent edits in different workspaces.
 *
 * @group workspaces
 */
class WorkspaceConcurrentEditingTest extends BrowserTestBase {

  use WorkspaceTestUtilities;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'workspaces'];

  /**
   * Test switching workspace via the switcher block and admin page.
   */
  public function testSwitchingWorkspaces() {
    $permissions = [
      'create workspace',
      'edit own workspace',
      'view own workspace',
      'bypass entity access own workspace',
    ];

    $mayer = $this->drupalCreateUser($permissions);
    $this->drupalLogin($mayer);
    $this->setupWorkspaceSwitcherBlock();

    // Create a test node.
    $this->createContentType(['type' => 'test', 'label' => 'Test']);
    $test_node = $this->createNodeThroughUi('Test node', 'test');

    // Check that the user can edit the node.
    $page = $this->getSession()->getPage();
    $page->hasField('title[0][value]');

    // Create two workspaces.
    $vultures = $this->createWorkspaceThroughUi('Vultures', 'vultures');
    $gravity = $this->createWorkspaceThroughUi('Gravity', 'gravity');

    // Edit the node in workspace 'vultures'.
    $this->switchToWorkspace($vultures);
    $this->drupalGet('/node/' . $test_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $page->fillField('Title', 'Test node - override');
    $page->findButton('Save')->click();

    // Check that the user can still edit the node in the same workspace.
    $this->drupalGet('/node/' . $test_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasField('title[0][value]'));

    // Switch to a different workspace and check that the user can not edit the
    // node anymore.
    $this->switchToWorkspace($gravity);
    $this->drupalGet('/node/' . $test_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->hasField('title[0][value]'));
    $page->hasContent('The content is being edited in the Vultures workspace.');

    // Check that the node fails validation for API calls.
    $violations = $test_node->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The content is being edited in the <em class="placeholder">Vultures</em> workspace. As a result, your changes cannot be saved.', $violations->get(0)->getMessage());

    // Switch to the Live workspace and check that the user still can not edit
    // the node.
    $live = Workspace::load('live');
    $this->switchToWorkspace($live);
    $this->drupalGet('/node/' . $test_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->hasField('title[0][value]'));
    $page->hasContent('The content is being edited in the Vultures workspace.');

    // Check that the node fails validation for API calls.
    $violations = $test_node->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('The content is being edited in the <em class="placeholder">Vultures</em> workspace. As a result, your changes cannot be saved.', $violations->get(0)->getMessage());

    // Deploy the changes from the 'Vultures' workspace and check that the node
    // can be edited again in other workspaces.
    $vultures->publish();
    $this->switchToWorkspace($gravity);
    $this->drupalGet('/node/' . $test_node->id() . '/edit');
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->hasField('title[0][value]'));
  }

}
