<?php

namespace Drush\User;

class UserSingle7 extends UserSingleBase {

  public function block() {
    user_user_operations_block(array($this->account->uid));
  }

  public function unblock() {
    user_user_operations_unblock(array($this->account->uid));
  }

  public function addRole($rid) {
    user_multiple_role_edit(array($this->account->uid), 'add_role', $rid);
  }

  public function removeRole($rid) {
    user_multiple_role_edit(array($this->account->uid), 'remove_role', $rid);
  }

  function info() {
    $userinfo = (array)$this->account;
    unset($userinfo['data']);
    unset($userinfo['block']);
    unset($userinfo['form_build_id']);
    foreach (array('created', 'access', 'login') as $key) {
      $userinfo['user_' . $key] = drush_format_date($userinfo[$key]);
    }
    $userinfo['user_status'] = $userinfo['status'] ? 'active' : 'blocked';
    return $userinfo;
  }

  function password($pass) {
    user_save($this->account, array('pass' => $pass));
  }

  public function getUsername() {
    return $this->account->name;
  }

  public function id() {
    return $this->account->uid;
  }
}
