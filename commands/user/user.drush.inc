<?php

/**
 * Get a version specific UserSingle class.
 *
 * @param $account
 * @return \Drush\User\UserSingleBase
 *
 * @see drush_get_class().
 */
function drush_usersingle_get_class($account) {
  return drush_get_class('Drush\User\UserSingle', array($account));
}

/**
 * Get a version specific User class.
 *
 * @return \Drush\User\UserVersion
 *
 * @see drush_get_class().
 */
function drush_user_get_class() {
  return drush_get_class('Drush\User\User');
}
