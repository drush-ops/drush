<?php

namespace Drush\User;

class UserSingle9 extends UserSingle8 {

  /**
   * A flatter and simpler array presentation of a Drupal $user object.
   *
   * @return array
   */
  public function info() {
    return array(
      'uid' => $this->account->id(),
      'name' => $this->account->getAccountName(),
      'password' => $this->account->getPassword(),
      'mail' => $this->account->getEmail(),
      'user_created' => $this->account->getCreatedTime(),
      'created' => drush_format_date($this->account->getCreatedTime()),
      'user_access' => $this->account->getLastAccessedTime(),
      'access' => drush_format_date($this->account->getLastAccessedTime()),
      'user_login' => $this->account->getLastLoginTime(),
      'login' => drush_format_date($this->account->getLastLoginTime()),
      'user_status' => $this->account->get('status')->value,
      'status' => $this->account->isActive() ? 'active' : 'blocked',
      'timezone' => $this->account->getTimeZone(),
      'roles' => $this->account->getRoles(),
      'langcode' => $this->account->getPreferredLangcode(),
      'uuid' => $this->account->uuid->value,
    );
  }

}
