<?php

function drush_get_user_name(Drupal\user\Entity\User $user = NULL) {
  if (empty($user)) {
    $user = \Drupal::currentUser();
  }
  return $user->getUsername();
}

function drush_get_user_id(Drupal\user\Entity\User $user = NULL) {
  if (empty($user)) {
    $user = \Drupal::currentUser();
  }
  return $user->id();
}
