<?php

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\system\Functional\Entity\EntityWithUriCacheTagsTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Tests the User entity's cache tags.
 *
 * @group user
 */
class UserCacheTagsTest extends EntityWithUriCacheTagsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Give anonymous users permission to view user profiles, so that we can
    // verify the cache tags of cached versions of user profile pages.
    $user_role = Role::load(RoleInterface::ANONYMOUS_ID);
    $user_role->grantPermission('access user profiles');
    $user_role->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "Llama" user.
    $user = User::create([
      'name' => 'Llama',
      'status' => TRUE,
    ]);
    $user->save();

    return $user;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalCacheTagsForEntityListing() {
    return ['user:0', 'user:1'];
  }

}
