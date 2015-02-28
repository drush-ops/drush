<?php

namespace Drush\User;

class UserSingle6 extends UserSingle7 {

  public function cancel() {
    user_delete(array(), $this->account->uid);
  }
}
