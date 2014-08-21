<?php

namespace Drush\User;

abstract class UserSingleBase {

  // A Drupal user entity.
  public $account;

  /**
   * Finds a list of user objects based on Drush arguments,
   * or options.
   */
  public function __construct($account) {
    $this->account = $account;
  }

  public function info() {
    return array(
      'uid' => $this->account->id(),
      'name' => $this->account->getUsername(),
      'password' => $this->account->getPassword(),
      'mail' => $this->account->getEmail(),
      'signature' => $this->account->getSignature(),
      'signature_format' => $this->account->getSignatureFormat(),
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

  public function block() {
    $this->account->block();
    $this->account->save();
  }

  public function unblock() {
    $this->account->get('status')->value = 1;
    $this->account->save();
  }

  public function addRole($rid) {
    $this->account->addRole($rid);
    $this->account->save();
  }

  public function removeRole($rid) {
    $this->account->removeRole($rid);
    $this->account->save();
  }

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

  public function password($password) {
    $this->account->setPassword($password);
    $this->account->save();
  }

  public function passResetUrl($path = '') {
    $links = array();
    $options = array();
    if ($path) {
      $options['query']['destination'] = $path;
    }
    return url(user_pass_reset_url($this->account), $options);
  }

  public function getUsername() {
    return $this->account->getUsername();
  }

  public function id() {
    return $this->account->id();
  }
}
