<?php

namespace Drupal\Tests\user\Functional\Rest;

use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\user\Entity\Role;

abstract class RoleResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['user'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'user_role';

  /**
   * @var \Drupal\user\RoleInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer permissions']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $role = Role::create([
      'id' => 'llama',
      'name' => $this->randomString(),
    ]);
    $role->save();

    return $role;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return [
      'uuid' => $this->entity->uuid(),
      'weight' => 2,
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'llama',
      'label' => NULL,
      'is_admin' => NULL,
      'permissions' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

}
