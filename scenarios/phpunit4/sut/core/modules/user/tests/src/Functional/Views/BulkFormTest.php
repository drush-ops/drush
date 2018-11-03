<?php

namespace Drupal\Tests\user\Functional\Views;

use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\views\Views;

/**
 * Tests a user bulk form.
 *
 * @group user
 * @see \Drupal\user\Plugin\views\field\UserBulkForm
 */
class BulkFormTest extends UserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['views_ui'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_user_bulk_form', 'test_user_bulk_form_combine_filter'];

  /**
   * Tests the user bulk form.
   */
  public function testBulkForm() {
    // Log in as a user without 'administer users'.
    $this->drupalLogin($this->drupalCreateUser(['administer permissions']));
    $user_storage = $this->container->get('entity.manager')->getStorage('user');

    // Create an user which actually can change users.
    $this->drupalLogin($this->drupalCreateUser(['administer users']));
    $this->drupalGet('test-user-bulk-form');
    $result = $this->cssSelect('#edit-action option');
    $this->assertTrue(count($result) > 0);

    // Test submitting the page with no selection.
    $edit = [
      'action' => 'user_block_user_action',
    ];
    $this->drupalPostForm('test-user-bulk-form', $edit, t('Apply to selected items'));
    $this->assertText(t('No users selected.'));

    // Assign a role to a user.
    $account = $user_storage->load($this->users[0]->id());
    $roles = user_role_names(TRUE);
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    $role = key($roles);

    $this->assertFalse($account->hasRole($role), 'The user currently does not have a custom role.');
    $edit = [
      'user_bulk_form[1]' => TRUE,
      'action' => 'user_add_role_action.' . $role,
    ];
    $this->drupalPostForm(NULL, $edit, t('Apply to selected items'));
    // Re-load the user and check their roles.
    $user_storage->resetCache([$account->id()]);
    $account = $user_storage->load($account->id());
    $this->assertTrue($account->hasRole($role), 'The user now has the custom role.');

    $edit = [
      'user_bulk_form[1]' => TRUE,
      'action' => 'user_remove_role_action.' . $role,
    ];
    $this->drupalPostForm(NULL, $edit, t('Apply to selected items'));
    // Re-load the user and check their roles.
    $user_storage->resetCache([$account->id()]);
    $account = $user_storage->load($account->id());
    $this->assertFalse($account->hasRole($role), 'The user no longer has the custom role.');

    // Block a user using the bulk form.
    $this->assertTrue($account->isActive(), 'The user is not blocked.');
    $this->assertRaw($account->label(), 'The user is found in the table.');
    $edit = [
      'user_bulk_form[1]' => TRUE,
      'action' => 'user_block_user_action',
    ];
    $this->drupalPostForm(NULL, $edit, t('Apply to selected items'));
    // Re-load the user and check their status.
    $user_storage->resetCache([$account->id()]);
    $account = $user_storage->load($account->id());
    $this->assertTrue($account->isBlocked(), 'The user is blocked.');
    $this->assertNoRaw($account->label(), 'The user is not found in the table.');

    // Remove the user status filter from the view.
    $view = Views::getView('test_user_bulk_form');
    $view->removeHandler('default', 'filter', 'status');
    $view->storage->save();

    // Ensure the anonymous user is found.
    $this->drupalGet('test-user-bulk-form');
    $this->assertText($this->config('user.settings')->get('anonymous'));

    // Attempt to block the anonymous user.
    $edit = [
      'user_bulk_form[0]' => TRUE,
      'action' => 'user_block_user_action',
    ];
    $this->drupalPostForm(NULL, $edit, t('Apply to selected items'));
    $anonymous_account = $user_storage->load(0);
    $this->assertTrue($anonymous_account->isBlocked(), 'Ensure the anonymous user got blocked.');

    // Test the list of available actions with a value that contains a dot.
    $this->drupalLogin($this->drupalCreateUser(['administer permissions', 'administer views', 'administer users']));
    $action_id = 'user_add_role_action.' . $role;
    $edit = [
      'options[include_exclude]' => 'exclude',
      "options[selected_actions][$action_id]" => $action_id,
    ];
    $this->drupalPostForm('admin/structure/views/nojs/handler/test_user_bulk_form/default/field/user_bulk_form', $edit, t('Apply'));
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->drupalGet('test-user-bulk-form');
    $this->assertNoOption('edit-action', $action_id);
    $edit['options[include_exclude]'] = 'include';
    $this->drupalPostForm('admin/structure/views/nojs/handler/test_user_bulk_form/default/field/user_bulk_form', $edit, t('Apply'));
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->drupalGet('test-user-bulk-form');
    $this->assertOption('edit-action', $action_id);
  }

  /**
   * Tests the user bulk form with a combined field filter on the bulk column.
   */
  public function testBulkFormCombineFilter() {
    // Add a user.
    User::load($this->users[0]->id());
    $view = Views::getView('test_user_bulk_form_combine_filter');
    $errors = $view->validate();
    $this->assertEqual(reset($errors['default']), t('Field %field set in %filter is not usable for this filter type. Combined field filter only works for simple fields.', ['%field' => 'User: Bulk update', '%filter' => 'Global: Combine fields filter']));
  }

}
