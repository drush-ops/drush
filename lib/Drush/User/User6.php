<?php

namespace Drush\User;

class User6 extends User7 {

  /**
   * {inheritdoc}
   */
  public function create($properties) {
    $account = user_save(NULL, $properties, NULL);
    return new UserSingle6($account);
  }

  /**
   * {@inheritdoc}
   */
  public function load_by_name($name) {
    return user_load(array('name' => $name));
  }

  /**
   * {@inheritdoc}
   */
  public function load_by_mail($mail) {
    return user_load(array('mail' => $mail));
  }

}
