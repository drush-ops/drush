<?php

namespace Drupal\webprofiler\Entity\Decorators\Config;

use Drupal\Core\Session\AccountInterface;
use Drupal\shortcut\ShortcutSetInterface;
use Drupal\shortcut\ShortcutSetStorageInterface;

/**
 * Class ShortcutSetStorageDecorator
 */
class ShortcutSetStorageDecorator extends ConfigEntityStorageDecorator implements ShortcutSetStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function assignUser(ShortcutSetInterface $shortcut_set, $account) {
    $this->getOriginalObject()->assignUser($shortcut_set, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function unassignUser($account) {
    return $this->getOriginalObject()->unassignUser($account);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAssignedShortcutSets(ShortcutSetInterface $entity) {
    $this->getOriginalObject()->deleteAssignedShortcutSets($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getAssignedToUser($account) {
    return $this->getOriginalObject()->getAssignedToUser($account);
  }

  /**
   * {@inheritdoc}
   */
  public function countAssignedUsers(ShortcutSetInterface $shortcut_set) {
    return $this->getOriginalObject()->countAssignedUsers($shortcut_set);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSet(AccountInterface $account) {
    return $this->getOriginalObject()->getDefaultSet($account);
  }

}
