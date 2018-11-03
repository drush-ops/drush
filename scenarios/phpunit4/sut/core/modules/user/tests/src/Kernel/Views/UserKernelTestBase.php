<?php

namespace Drupal\Tests\user\Kernel\Views;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Provides a common test base for user views tests.
 */
abstract class UserKernelTestBase extends ViewsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['user_test_views', 'user', 'system', 'field'];

  /**
   * Users to use during this test.
   *
   * @var array
   */
  protected $users = [];

  /**
   * The entity storage for roles.
   *
   * @var \Drupal\user\RoleStorage
   */
  protected $roleStorage;

  /**
   * The entity storage for users.
   *
   * @var \Drupal\user\UserStorage
   */
  protected $userStorage;

  protected function setUp($import_test_views = TRUE) {
    parent::setUp();

    ViewTestData::createTestViews(get_class($this), ['user_test_views']);

    $this->installEntitySchema('user');

    $entity_manager = $this->container->get('entity.manager');
    $this->roleStorage = $entity_manager->getStorage('user_role');
    $this->userStorage = $entity_manager->getStorage('user');
  }

  /**
   * Set some test data for permission related tests.
   */
  protected function setupPermissionTestData() {
    // Setup a role without any permission.
    $this->roleStorage->create(['id' => 'authenticated'])
      ->save();
    $this->roleStorage->create(['id' => 'no_permission'])
      ->save();
    // Setup a role with just one permission.
    $this->roleStorage->create(['id' => 'one_permission'])
      ->save();
    user_role_grant_permissions('one_permission', ['administer permissions']);
    // Setup a role with multiple permissions.
    $this->roleStorage->create(['id' => 'multiple_permissions'])
      ->save();
    user_role_grant_permissions('multiple_permissions', ['administer permissions', 'administer users', 'access user profiles']);

    // Setup a user without an extra role.
    $this->users[] = $account = $this->userStorage->create(['name' => $this->randomString()]);
    $account->save();
    // Setup a user with just the first role (so no permission beside the
    // ones from the authenticated role).
    $this->users[] = $account = $this->userStorage->create(['name' => 'first_role']);
    $account->addRole('no_permission');
    $account->save();
    // Setup a user with just the second role (so one additional permission).
    $this->users[] = $account = $this->userStorage->create(['name' => 'second_role']);
    $account->addRole('one_permission');
    $account->save();
    // Setup a user with both the second and the third role.
    $this->users[] = $account = $this->userStorage->create(['name' => 'second_third_role']);
    $account->addRole('one_permission');
    $account->addRole('multiple_permissions');
    $account->save();
  }

}
