<?php

namespace Drush\User;

abstract class UserSingleBase {

  // A Drupal user entity.
  public $account;

  public function __construct($account) {
    $this->account = $account;
  }

  /**
   * A flatter and simpler array presentation of a Drupal $user object.
   *
   * @return array
   */
  public function info() {
    return array(
      'uid' => $this->account->id(),
      'name' => $this->account->getUsername(),
      'password' => $this->account->getPassword(),
      'mail' => $this->account->getEmail(),
      'user_created' => $this->account->getCreatedTime(),
      'created' => format_date($this->account->getCreatedTime()),
      'user_access' => $this->account->getLastAccessedTime(),
      'access' => format_date($this->account->getLastAccessedTime()),
      'user_login' => $this->account->getLastLoginTime(),
      'login' => format_date($this->account->getLastLoginTime()),
      'user_status' => $this->account->get('status')->value,
      'status' => $this->account->isActive() ? 'active' : 'blocked',
      'timezone' => $this->account->getTimeZone(),
      'roles' => $this->account->getRoles(),
      'langcode' => $this->account->getPreferredLangcode(),
      'uuid' => $this->account->uuid->value,
    );
  }

  /**
   * Block a user from login.
   */
  public function block() {
    $this->account->block();
    $this->account->save();
  }

  /**
   * Unblock a user from login.
   */
  public function unblock() {
    $this->account->get('status')->value = 1;
    $this->account->save();
  }

  /**
   * Add a role to the current user.
   *
   * @param $rid
   *   A role ID.
   */
  public function addRole($rid) {
    $this->account->addRole($rid);
    $this->account->save();
  }

  /**
   * Remove a role from the current user.
   *
   * @param $rid
   *   A role ID.
   */
  public function removeRole($rid) {
    $this->account->removeRole($rid);
    $this->account->save();
  }

  /**
   * Block a user and remove or reassign their content.
   */
  public function cancel() {
      if (drush_get_option('delete-content')) {
        user_cancel(array(), $this->id(), 'user_cancel_delete');
      }
      else {
        user_cancel(array(), $this->id(), 'user_cancel_reassign');
      }
      // I got the following technique here: http://drupal.org/node/638712
      $batch =& batch_get();
      $batch['progressive'] = FALSE;
      batch_process();
  }

  /**
   * Change a user's password.
   *
   * @param $password
   */
  public function password($password) {
    $this->account->setPassword($password);
    $this->account->save();
  }

  /**
   * Build a one time login link.
   *
   * @param string $path
   * @return string
   */
  public function passResetUrl($path = '') {
    $url = user_pass_reset_url($this->account) . '/login';
    if ($path) {
      $url .= '?destination=' . $path;
    }
    return $url;
  }

  /**
   * Get a user's name.
   * @return string
   */
  public function getUsername() {
    return $this->account->getUsername();
  }

  /**
   * Return an id from a Drupal user account.
   * @return int
   */
  public function id() {
    return $this->account->id();
  }
}
