<?php

namespace Drush\User;

class User7 extends UserVersion {

  /**
   * {inheritdoc}
   */
  public function create($properties) {
    $account = user_save(NULL, $properties, NULL);
    return $account;
  }
}
