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

/**
 * @param int $uid
 * @return Drupal\user\Entity\User
 */
function drush_user_load($uid) {
  return user_load($uid);
}

function drush_user_info_single($account) {
  $return = array(
    'uid' => $account->id(),
    'name' => drush_get_user_name($account),
    'password' => $account->getPassword(),
    'mail' => $account->getEmail(),
    'theme' => $account->getDefaultTheme(),
    'signature' => $account->getSignature(),
    'signature_format' => $account->getSignatureFormat(),
    'user_created' => $account->getCreatedTime(),
    'created' => format_date($account->getCreatedTime()),
    'user_access' => $account->getLastAccessedTime(),
    'access' => format_date($account->getLastAccessedTime()),
    'user_login' => $account->getLastLoginTime(),
    'login' => format_date($account->getLastLoginTime()),
    'user_status' => $account->get('status')->value,
    'status' => $account->isActive() ? 'active' : 'blocked',
    'timezone' => $account->getTimeZone(),
    'roles' => $account->getRoles(),
    'langcode' => $account->getPreferredLangcode(),
    'uuid' => $account->uuid->value,
  );
  return $return;
}

function _drush_user_block($uids) {
  foreach ($uids as $uid) {
    if ($account = user_load($uid)) {
      $account->block();
      $account->save();
    }
  }
}

function _drush_user_unblock($uids) {
  foreach ($uids as $uid) {
    if ($account = user_load($uid)) {
      $account->get('status')->value = 1;
      $account->save();
    }
  }
}

function _drush_user_add_role($uids, $rid) {
  foreach ($uids as $uid) {
    if ($account = user_load($uid)) {
      $account->addRole($rid);
      $account->save();
    }
  }
}

function _drush_user_remove_role($uids, $rid) {
  foreach ($uids as $uid) {
    if ($account = user_load($uid)) {
      $account->removeRole($rid);
      $account->save();
    }
  }
}

function _drush_user_password($user, $pass) {
  $user->setPassword($pass);
  $user->save();
  return $user;
}

function _drush_user_create($props) {
  $account = entity_create('user', $props);
  $account->save();
  return $account;
}
