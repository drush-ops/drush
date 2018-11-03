<?php

namespace Drupal\Tests\workspaces\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the workspace entity.
 *
 * @group workspaces
 */
class WorkspaceTest extends BrowserTestBase {

  use WorkspaceTestUtilities;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['workspaces'];

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $editor1;

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $editor2;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $permissions = [
      'access administration pages',
      'administer site configuration',
      'create workspace',
      'edit own workspace',
      'edit any workspace',
    ];

    $this->editor1 = $this->drupalCreateUser($permissions);
    $this->editor2 = $this->drupalCreateUser($permissions);
  }

  /**
   * Test creating a workspace with special characters.
   */
  public function testSpecialCharacters() {
    $this->drupalLogin($this->editor1);

    // Test a valid workspace name.
    $this->createWorkspaceThroughUi('Workspace 1', 'a0_$()+-/');

    // Test and invalid workspace name.
    $this->drupalGet('/admin/config/workflow/workspaces/add');
    $this->assertSession()->statusCodeEquals(200);

    $page = $this->getSession()->getPage();
    $page->fillField('label', 'workspace2');
    $page->fillField('id', 'A!"£%^&*{}#~@?');
    $page->findButton('Save')->click();
    $page->hasContent("This value is not valid");
  }

  /**
   * Test changing the owner of a workspace.
   */
  public function testWorkspaceOwner() {
    $this->drupalLogin($this->editor1);

    $this->drupalPostForm('/admin/config/workflow/workspaces/add', [
      'id' => 'test_workspace',
      'label' => 'Test workspace',
    ], 'Save');

    $storage = \Drupal::entityTypeManager()->getStorage('workspace');
    $test_workspace = $storage->load('test_workspace');
    $this->assertEquals($this->editor1->id(), $test_workspace->getOwnerId());

    $this->drupalPostForm('/admin/config/workflow/workspaces/manage/test_workspace/edit', [
      'uid[0][target_id]' => $this->editor2->getUsername(),
    ], 'Save');

    $test_workspace = $storage->loadUnchanged('test_workspace');
    $this->assertEquals($this->editor2->id(), $test_workspace->getOwnerId());
  }

  /**
   * Tests that editing a workspace creates a new revision.
   */
  public function testWorkspaceFormRevisions() {
    $this->drupalLogin($this->editor1);
    $storage = \Drupal::entityTypeManager()->getStorage('workspace');

    // The current live workspace entity should be revision 1.
    $live_workspace = $storage->load('live');
    $this->assertEquals('1', $live_workspace->getRevisionId());

    // Re-save the live workspace via the UI to create revision 3.
    $this->drupalPostForm($live_workspace->url('edit-form'), [], 'Save');
    $live_workspace = $storage->loadUnchanged('live');
    $this->assertEquals('3', $live_workspace->getRevisionId());
  }

}
