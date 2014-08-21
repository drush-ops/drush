<?php

namespace Drush\User;

class User8 extends UserVersion {

  /**
   * {inheritdoc}
   */
  public function create($properties) {
    $account = entity_create('user', $properties);
    $account->save();
    return $account;
  }
}
