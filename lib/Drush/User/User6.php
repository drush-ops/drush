<?php

namespace Drush\User;

class User6 extends User7 {

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
