<?php

namespace Drupal\user;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the user entity type.
 *
 * @see \Drupal\user\Entity\User
 */
class UserAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Allow access to user label.
   *
   * @var bool
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\user\UserInterface $entity*/

    // We don't treat the user label as privileged information, so this check
    // has to be the first one in order to allow labels for all users to be
    // viewed, including the special anonymous user.
    if ($operation === 'view label') {
      return AccessResult::allowed();
    }

    // The anonymous user's profile can neither be viewed, updated nor deleted.
    if ($entity->isAnonymous()) {
      return AccessResult::forbidden();
    }

    // Administrators can view/update/delete all user profiles.
    if ($account->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        // Only allow view access if the account is active.
        if ($account->hasPermission('access user profiles') && $entity->isActive()) {
          return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
        }
        // Users can view own profiles at all times.
        elseif ($account->id() == $entity->id()) {
          return AccessResult::allowed()->cachePerUser();
        }
        else {
          return AccessResultNeutral::neutral("The 'access user profiles' permission is required and the user must be active.")->cachePerPermissions()->addCacheableDependency($entity);
        }
        break;

      case 'update':
        // Users can always edit their own account.
        return AccessResult::allowedIf($account->id() == $entity->id())->cachePerUser();

      case 'delete':
        // Users with 'cancel account' permission can cancel their own account.
        return AccessResult::allowedIf($account->id() == $entity->id() && $account->hasPermission('cancel account'))->cachePerPermissions()->cachePerUser();
    }

    // No opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    // Fields that are not implicitly allowed to administrative users.
    $explicit_check_fields = [
      'pass',
    ];

    // Administrative users are allowed to edit and view all fields.
    if (!in_array($field_definition->getName(), $explicit_check_fields) && $account->hasPermission('administer users')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Flag to indicate if this user entity is the own user account.
    $is_own_account = $items ? $items->getEntity()->id() == $account->id() : FALSE;
    switch ($field_definition->getName()) {
      case 'name':
        // Allow view access to anyone with access to the entity. Anonymous
        // users should be able to access the username field during the
        // registration process, otherwise the username and email constraints
        // are not checked.
        if ($operation == 'view' || ($items && $account->isAnonymous() && $items->getEntity()->isAnonymous())) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        // Allow edit access for the own user name if the permission is
        // satisfied.
        if ($is_own_account && $account->hasPermission('change own username')) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser();
        }
        else {
          return AccessResult::neutral();
        }

      case 'preferred_langcode':
      case 'preferred_admin_langcode':
      case 'timezone':
      case 'mail':
        // Allow view access to own mail address and other personalization
        // settings.
        if ($operation == 'view') {
          return $is_own_account ? AccessResult::allowed()->cachePerUser() : AccessResult::neutral();
        }
        // Anyone that can edit the user can also edit this field.
        return AccessResult::allowed()->cachePerPermissions();

      case 'pass':
        // Allow editing the password, but not viewing it.
        return ($operation == 'edit') ? AccessResult::allowed() : AccessResult::forbidden();

      case 'created':
        // Allow viewing the created date, but not editing it.
        return ($operation == 'view') ? AccessResult::allowed() : AccessResult::neutral();

      case 'roles':
      case 'status':
      case 'access':
      case 'login':
      case 'init':
        return AccessResult::neutral();
    }

    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
