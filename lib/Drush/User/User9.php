<?php


namespace Drush\User;

use Drupal\user\Entity\User;

class User9 extends User8 {

  /**
   * {inheritdoc}
   */
  public function create($properties) {
    $account = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->create($properties);
    $account->save();
    return new UserSingle9($account);
  }
}
