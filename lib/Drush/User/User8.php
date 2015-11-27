<?php


namespace Drush\User;

use Drupal\user\Entity\User;

class User8 extends UserVersion {

  /**
   * {inheritdoc}
   */
  public function create($properties) {
    $account = entity_create('user', $properties);
    $account->save();
    return new UserSingle8($account);
  }

  /**
   * Attempt to load a user account.
   *
   * @param int $uid
   * @return \Drupal\user\Entity\User;
   */
  public function load_by_uid($uid) {
    return User::load($uid);
  }

  /**
   * {inheritdoc}
   */
  public function getCurrentUserAsAccount() {
    return \Drupal::currentUser()->getAccount();
  }

  /**
   * Set the current user in Drupal.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   */
  public function setCurrentUser($account) {
    // Some parts of Drupal still rely on a global user object.
    // @todo remove once https://www.drupal.org/node/2163205 is in.
    global $user;
    $user = $account;
    \Drupal::currentUser()->setAccount($account);
  }
}
