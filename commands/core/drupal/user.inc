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
