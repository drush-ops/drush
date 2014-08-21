<?php

namespace Drush\User;

abstract class UserVersion {

  /**
   * Create a new user account.
   *
   * @return
   *   A user object.
   */
  public function create($properties) {}

  public function load_by_uid($uid) {
    return user_load($uid);
  }

  public function load_by_name($name) {
    return user_load_by_name($name);
  }

  public function load_by_mail($mail) {
    return user_load_by_mail($mail);
  }
}
