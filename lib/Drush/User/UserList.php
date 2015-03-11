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
    if ($this->accounts = $this->getFromOptions() + $this->getFromParameters($inputs)) {
      return $this;
    }
    else {
      throw new UserListException('Unable to find a matching user.');
    }
  }

  /**
   * Iterate over each account and call the specified method.
   *
   * @param $method
   *   A method on a UserSingleBase object.
   * @param array $params
   *   An array of params to pass to the method.
   * @return array
   *   An associate array of values keyed by account ID.
   */
  public function each($method, array $params = array()) {
    foreach ($this->accounts as $account) {
      $return[$account->id()] = call_user_func_array(array($account, $method), $params);
    }
    return $return;
  }

  /*
   * Check common options for specifying users. If valid, return the accounts.
   *
   * @return \Drush\User\UserSingleBase[]
   */
  function getFromOptions() {
    $accounts = array();
    $userversion = drush_user_get_class();
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
   * @param string $inputs
   *   A comma delimited string (or array) of arguments, specifying user account(s).
   *
   * @throws UserListException
   *   If any input is unmatched, an exception is thrown.
   *
   * @return \Drush\User\UserSingleBase[]
   *   An associative array of UserSingleBase objects, keyed by user id.
   */
  public static function getFromParameters($inputs) {
    $accounts = array();
    $userversion = drush_user_get_class();
    if ($inputs && $userversion) {
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

  /*
   * A comma delimited list of names built from $this->accounts.
   */
  public function names() {
    $names = array();
    foreach ($this->accounts as $account) {
      $names[] = $account->getUsername();
    }
    return implode(', ', $names);
  }
}
