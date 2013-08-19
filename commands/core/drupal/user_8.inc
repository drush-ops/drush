<?php

function drush_get_user_name($user = NULL) {
  if (empty($user)) {
    // @todo this no longer the way.
    global $user;
  }
  return $user->getUsername();
}

function drush_get_user_id($user = NULL) {
  if (empty($user)) {
    // @todo this no longer the way.
    global $user;
  }
  return $user->id();
}
