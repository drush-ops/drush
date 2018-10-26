<?php

namespace Drupal\alinks;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Keyword entity.
 *
 * @see \Drupal\alinks\Entity\Keyword.
 */
class KeywordAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\alinks\Entity\KeywordInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished keyword entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published keyword entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit keyword entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete keyword entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add keyword entities');
  }

}
