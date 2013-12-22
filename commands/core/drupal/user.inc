<?php

function drush_get_user_name($user = NULL) {
  if (empty($user)) {
    global $user;
  }
  return $user->name;
}

function drush_get_user_id($user = NULL) {
  if (empty($user)) {
    global $user;
  }
  return $user->uid;
}

function drush_user_load($uid) {
  if (drush_drupal_major_version() >= 7) {
    $userinfo = user_load($uid);
  }
  else {
    $userinfo = user_load(array('uid' => $uid));
  }
  return $userinfo;
}

function drush_user_info_single($userinfo) {
  $userinfo = (array)$userinfo;
  unset($userinfo['data']);
  unset($userinfo['block']);
  unset($userinfo['form_build_id']);
  foreach (array('created', 'access', 'login') as $key) {
    $userinfo['user_' . $key] = format_date($userinfo[$key]);
  }
  $userinfo['user_status'] = $userinfo['status'] ? 'active' : 'blocked';
  return $userinfo;
}

function _drush_user_block($uids) {
  user_user_operations_block($uids);
}

function _drush_user_unblock($uids) {
  user_user_operations_unblock($uids);
}

function _drush_user_add_role($uids, $rid) {
  user_multiple_role_edit($uids, 'add_role', $rid);
}

function _drush_user_remove_role($uids, $rid) {
  user_multiple_role_edit($uids, 'remove_role', $rid);
}

function _drush_user_password($user, $pass) {
  return user_save($user, array('pass' => $pass));
}

function _drush_user_create($props) {
  $account = user_save(NULL, $props, NULL);
  return $account;
}
