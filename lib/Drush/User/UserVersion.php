<?php

namespace Drush\User;

abstract class UserVersion {

  /**
   * Create a new user account.
   *
   * @param array $properties
   *
   * @return
   *   A user object.
   */
  public function create($properties) {}

  /**
   * Attempt to load a user account.
   *
   * @param int $uid
   * @return mixed
   */
  public function load_by_uid($uid) {
    return user_load($uid);
  }

  /**
   * Attempt to load a user account.
   *
   * @param string $name
   * @return mixed
   */
  public function load_by_name($name) {
    return user_load_by_name($name);
  }

  /**
   * Attempt to load a user account.
   *
   * @param string $mail
   * @return mixed
   */
  public function load_by_mail($mail) {
    return user_load_by_mail($mail);
  }

  /**
   * Load the current user account.
   *
   * @return mixed
   *   A user object.
   */
  public function getCurrentUserAsAccount() {
    global $user;
    return $user;
  }

  /**
   * Load the current user account and return a UserSingle instance.
   *
   * @return \Drush\User\UserSingleBase
   *   A Drush UserSingle instance.
   */
  public function getCurrentUserAsSingle() {
    return drush_usersingle_get_class($this->getCurrentUserAsAccount());
  }

  /**
   * Set the current "global" user account in Drupal.

   * @param
   *   A user object.
   */
  public function setCurrentUser($account) {
    global $user;
    $user = $account;
  }
}
