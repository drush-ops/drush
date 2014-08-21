<?php

namespace Drush\User;

class UserList {

  /** @var \Drush\User\UserSingleBase[] */
  public $accounts;

  /**
   * Finds a list of user objects based on Drush arguments,
   * or options.
   */
  public function __construct($inputs) {
    if ($this->accounts = $this->getFromOptions() + $this->getFromArguments($inputs)) {
      // Do nothing.
    }
    else {
      throw new UserListException('Unable to find a matching user.');
    }
  }

  function getFromOptions() {
    $accounts = array();
    $userversion = drush_user_get_class();
    // Unused?
    // $uids = drush_get_option_list('uids');
    if ($mails = _convert_csv_to_array(drush_get_option('mail'))) {
      foreach ($mails as $mail) {
        if ($account = $userversion->load_by_mail($mail)) {
          $single = drush_usersingle_get_class($account);
          $accounts[$single->id()] = $single;
        }
        else {
          throw new UserListException('Unable to find a matching user for ' . $mail . '.');
        }
      }
    }
    if ($names = _convert_csv_to_array(drush_get_option('name'))) {
      foreach ($names as $name) {
        if ($account = $userversion->load_by_name($name)) {
          $single = drush_usersingle_get_class($account);
          $accounts[$single->id()] = $single;
        }
        else {
          throw new UserListException('Unable to find a matching user for ' . $name . '.');
        }
      }
    }
    if ($userids = _convert_csv_to_array(drush_get_option('uid'))) {
      foreach ($userids as $userid) {
        if (is_numeric($userid) && $account = $userversion->load_by_uid($userid)) {
          $single = drush_usersingle_get_class($account);
          $accounts[$single->id()] = $single;
        }
        else {
          throw new UserListException('Unable to find a matching user for ' . $userid . '.');
        }
      }
    }
    return $accounts;
  }

  /**
   * Given a comma-separated list of inputs, return accounts
   * for users that match by uid,name or email address.
   *
   * @throws UserListException
   *   If any input is unmatched, an exception is thrown.
   *
   * @return array
   *   An associative array of $account objects, keyed by user id.
   */
  function getFromArguments($inputs) {
    $accounts = array();
    $userversion = drush_user_get_class();
    if ($inputs) {
      $inputs = _convert_csv_to_array($inputs);
      foreach($inputs as $input) {
        if (is_numeric($input) && $account = $userversion->load_by_uid($input)) {

        }
        elseif ($account = $userversion->load_by_name($input)) {

        }
        elseif ($account = $userversion->load_by_mail($input)) {

        }
        else {
          // Unable to load an account for the input.
          throw new UserListException('Unable to find a matching user for ' . $input . '.');
        }
        // Populate $accounts with a UserSingle object. Will go into $this->accounts.
        $single = drush_usersingle_get_class($account);
        $accounts[$single->id()] = $single;
      }
    }
    return $accounts;
  }

  public function info() {
    $return = array();
    foreach($this->accounts as $account) {
      $single = drush_usersingle_get_class($account);
      $return[$single->id()] = $single->info();
    }
    return $return;
  }

  public function block() {
    array_walk(array('self', 'block'), $this->accounts);
  }

  public function unblock() {
    foreach($this->accounts as $account) {
      $account->get('status')->value = 1;
      $account->save();
    }
  }

  public function addRole($rid) {
    foreach ($this->accounts as $account) {
      $account->addRole($rid);
      $account->save();
    }
  }

  public function removeRole($rid) {
    foreach ($this->accounts as $account) {
      $account->removeRole($rid);
      $account->save();
    }
  }

  public function cancel() {
    foreach ($this->accounts as $account) {
      if (drush_get_option('delete-content')) {
        user_cancel(array(), $account->uid, 'user_cancel_delete');
      }
      else {
        user_cancel(array(), $account->uid, 'user_cancel_reassign');
      }
      // I got the following technique here: http://drupal.org/node/638712
      $batch =& batch_get();
      $batch['progressive'] = FALSE;
      batch_process();
    }
  }

  public function password($password) {
    foreach ($this->accounts as $account) {
      $account->setPassword($password);
      $account->save();
    }
  }

  public function passResetUrl($path = '') {
    $links = array();
    $options = array();
    if ($path) {
      $options['query']['destination'] = $path;
    }
    foreach ($this->accounts as $account) {
      $links[$account->id()] = url(user_pass_reset_url($account), $options);
    }
    return $links;
  }

  public function names() {
    $names = array();
    foreach ($this->accounts as $account) {
      $names[] = $account->getUsername();
    }
    return implode(', ', $names);
  }
}
