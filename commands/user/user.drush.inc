<?php

use Drush\Log\LogLevel;
use Drush\User\UserList;
use Drush\User\UserListException;

/**
 * @file
 * Drush User Management commands
 */

function user_drush_help($section) {
  switch ($section) {
    case 'meta:user:title':
      return dt('User commands');
    case 'meta:user:summary':
      return dt('Add, modify and delete users.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function user_drush_command() {
  $options_common = array(
    'uid' => array(
      'description' => 'A comma delimited list of uids of users to operate on.',
      'example-value' => '3,5',
      'value' => 'required',
    ),
    'name' => array(
      'description' => 'A comma delimited list of user names of users to operate on.',
      'example-value' => 'foo',
      'value' => 'required',
    ),
    'mail' => array(
      'description' => 'A comma delimited list of user mail addresses of users to operate on.',
      'example-value' => 'me@example.com',
      'value' => 'required',
    )
  );

  $items['user-information'] = array(
    'description' => 'Print information about the specified user(s).',
    'aliases' => array('uinf', 'user:information'),
    'examples' => array(
      'drush user-information 2,3,someguy,somegal,billgates@microsoft.com' =>
        'Display information about the listed users.',
    ),
    'arguments' => array(
      'users' => 'A comma delimited list of uids, user names, or email addresses.',
    ),
    'required-arguments' => TRUE,
    'outputformat' => array(
      'default' => 'key-value-list',
      'pipe-format' => 'csv',
      'field-labels' => array(
        'uid' => 'User ID',
        'name' => 'User name',
        'pass' => 'Password',
        'mail' => 'User mail',
        'theme' => 'User theme',
        'signature' => 'Signature',
        'signature_format' => 'Signature format',
        'user_created' => 'User created',
        'created' => 'Created',
        'user_access' => 'User last access',
        'access' => 'Last access',
        'user_login' => 'User last login',
        'login' => 'Last login',
        'user_status' => 'User status',
        'status' => 'Status',
        'timezone' => 'Time zone',
        'picture' => 'User picture',
        'init' => 'Initial user mail',
        'roles' => 'User roles',
        'group_audience' => 'Group Audience',
        'langcode' => 'Language code',
        'uuid' => 'Uuid',
      ),
      'format-cell' => 'csv',
      'fields-default' => array('uid', 'name', 'mail', 'roles', 'user_status'),
      'fields-pipe' => array('name', 'uid', 'mail', 'status', 'roles'),
      'fields-full' => array('uid', 'name', 'pass', 'mail', 'theme', 'signature', 'user_created', 'user_access', 'user_login', 'user_status', 'timezone', 'roles', 'group_audience', 'langcode', 'uuid'),
      'output-data-type' => 'format-table',
    ),
  );
  $items['user-block'] = array(
    'description' => 'Block the specified user(s).',
    'aliases' => array('ublk', 'user:block'),
    'arguments' => array(
      'users' => 'A comma delimited list of uids, user names, or email addresses.',
    ),
    'examples' => array(
      'drush user-block 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' =>
        'Block the users with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => $options_common,
  );
  $items['user-unblock'] = array(
    'description' => 'Unblock the specified user(s).',
    'aliases' => array('uublk', 'user:unblock'),
    'arguments' => array(
      'users' => 'A comma delimited list of uids, user names, or email addresses.',
    ),
    'examples' => array(
      'drush user-unblock 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' =>
        'Unblock the users with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => $options_common,
  );
  $items['user-add-role'] = array(
    'description' => 'Add a role to the specified user accounts.',
    'aliases' => array('urol', 'user:add:role'),
    'arguments' => array(
      'role' => 'The name of the role to add',
      'users' => '(optional) A comma delimited list of uids, user names, or email addresses.',
    ),
    'required-arguments' => 1,
    'examples' => array(
      'drush user-add-role "power user" 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' =>
        'Add the "power user" role to the accounts with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => $options_common,
  );
  $items['user-remove-role'] = array(
    'description' => 'Remove a role from the specified user accounts.',
    'aliases' => array('urrol', 'user:remove:role'),
    'arguments' => array(
      'role' => 'The name of the role to remove',
      'users' => '(optional) A comma delimited list of uids, user names, or email addresses.',
    ),
    'required-arguments' => 1,
    'examples' => array(
      'drush user-remove-role "power user" 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' =>
        'Remove the "power user" role from the accounts with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => $options_common,
  );
  $items['user-create'] = array(
    'description' => 'Create a user account with the specified name.',
    'aliases' => array('ucrt', 'user:create'),
    'arguments' => array(
      'name' => 'The name of the account to add'
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush user-create newuser --mail="person@example.com" --password="letmein"' =>
        'Create a new user account with the name newuser, the email address person@example.com, and the password letmein',
    ),
    'options' => array(
      'password' => 'The password for the new account',
      'mail' => 'The email address for the new account',
    ),
    'outputformat' => $items['user-information']['outputformat'],
  );
  $items['user-cancel'] = array(
    'description' => 'Cancel a user account with the specified name.',
    'aliases' => array('ucan', 'user:cancel'),
    'arguments' => array(
      'name' => 'The name of the account to cancel',
    ),
    // The `_user_cancel` method still references global $user.
    // @todo remove once https://www.drupal.org/node/2163205 is in.
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    'required-arguments' => TRUE,
    'examples' => array(
      'drush user-cancel username' =>
        'Cancel the user account with the name username and anonymize all content created by that user.',
    ),
  );
  $items['user-password'] = array(
    'description' => '(Re)Set the password for the user account with the specified name.',
    'aliases' => array('upwd', 'user:password'),
    'arguments' => array(
      'name' => 'The name of the account to modify.'
    ),
    'required-arguments' => TRUE,
    'options' => array(
      'password' => array(
        'description' => 'The new password for the account.',
        'required' => TRUE,
        'example-value' => 'foo',
      ),
    ),
    'examples' => array(
      'drush user-password someuser --password="correct horse battery staple"' =>
        'Set the password for the username someuser. @see xkcd.com/936',
    ),
  );
  $items['user-login'] = array(
    'description' => 'Display a one time login link for the given user account (defaults to uid 1).',
    'aliases' => array('uli', 'user:login'),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'handle-remote-commands' => TRUE,
    'arguments' => array(
      'user' => 'An optional uid, user name, or email address for the user to log in as. Default is to log in as uid 1. The uid/name/mail options take priority if specified.',
      'path' => 'Optional path to redirect to after logging in.',
    ),
    'options' => array(
      'browser' => 'Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser.',
      'uid' => 'A uid to log in as.',
      'redirect-port' => 'A custom port for redirecting to (e.g. when running within a Vagrant environment)',
      'name' => 'A user name to log in as.',
      'mail' => 'A user mail address to log in as.',
    ),
    'examples' => array(
      'drush user-login ryan node/add/blog' => 'Displays and opens default web browser (if configured or detected) for a one-time login link for the user with the username ryan and redirect to the path node/add/blog.',
      'drush user-login --browser=firefox --mail=drush@example.org admin/settings/performance' => 'Open firefox web browser, login as the user with the e-mail address drush@example.org and redirect to the path admin/settings/performance.',
    ),
  );
  return $items;
}

/**
 * Implements hook_drush_help_alter().
 */
function user_drush_help_alter(&$command) {
  // Drupal 7+ only options.
  if ($command['command'] == 'user-cancel' && drush_drupal_major_version() >= 7) {
    $command['options']['delete-content'] = 'Delete all content created by the user';
    $command['examples']['drush user-cancel --delete-content username'] =
      'Cancel the user account with the name username and delete all content created by that user.';
  }
}

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

/**
 * Command callback. Prints information about the specified user(s).
 */
function drush_user_information($users) {
  $userlist = new UserList($users);
  $info = $userlist->each('info');
  return $info;
}

/**
 * Block the specified user(s).
 */
function drush_user_block($users = '') {
  $userlist = new UserList($users);
  $userlist->each('block');
  drush_log(dt('Blocked user(s): !users', array('!users' => $userlist->names())), LogLevel::SUCCESS);
}

/**
 * Unblock the specified user(s).
 */
function drush_user_unblock($users = '') {
  $userlist = new UserList($users);
  $userlist->each('unblock');
  drush_log(dt('Unblocked user(s): !users', array('!users' => $userlist->names())), LogLevel::SUCCESS);
}

/**
 * Add a role to the specified user accounts.
 */
function drush_user_add_role($role, $users = '') {
  // If role is not found, an exception gets thrown and handled by command invoke.
  $role_object = drush_role_get_class($role);
  $userlist = new UserList($users);
  $userlist->each('addRole', array($role_object->rid));
  drush_log(dt('Added role !role role to !users', array('!role' => $role, '!users' => $userlist->names())),LogLevel::SUCCESS);
}

/**
 * Remove a role from the specified user accounts.
 */
function drush_user_remove_role($role, $users = '') {
  // If role is not found, an exception gets thrown and handled by command invoke.
  $role_object = drush_role_get_class($role);
  $userlist = new UserList($users);
  $userlist->each('removeRole', array($role_object->rid));
  drush_log(dt('Removed !role role from !users', array('!role' => $role, '!users' => $userlist->names())),LogLevel::SUCCESS);
}

/**
 * Creates a new user account.
 */
function drush_user_create($name) {
  $userversion = drush_user_get_class();
  $mail = drush_get_option('mail');
  $pass = drush_get_option('password');
  $new_user = array(
    'name' => $name,
    'pass' => $pass,
    'mail' => $mail,
    'access' => '0',
    'status' => 1,
  );
  if (!drush_get_context('DRUSH_SIMULATE')) {
    if ($account = $userversion->create($new_user)) {
      return array($account->id() => $account->info());
    }
    else {
      return drush_set_error("Could not create a new user account with the name " . $name . ".");
    }
  }
}

function drush_user_create_validate($name) {
  $userversion = drush_user_get_class();
  if ($mail = drush_get_option('mail')) {
    if ($userversion->load_by_mail($mail)) {
      return drush_set_error(dt('There is already a user account with the email !mail', array('!mail' => $mail)));
    }
  }
  if ($userversion->load_by_name($name)) {
    return drush_set_error(dt('There is already a user account with the name !name', array('!name' => $name)));
  }
}

/**
 * Cancels a user account.
 */
function drush_user_cancel($inputs) {
  $userlist = new UserList($inputs);
  foreach ($userlist->accounts as $account) {
    if (drush_get_option('delete-content') && drush_drupal_major_version() >= 7) {
      drush_print(dt('All content created by !name will be deleted.', array('!name' => $account->getUsername())));
    }
    if (drush_confirm('Cancel user account?: ')) {
      $account->cancel();
    }
  }
  drush_log(dt('Cancelled user(s): !users', array('!users' => $userlist->names())),LogLevel::SUCCESS);
}

/**
 * Sets the password for the account with the given username
 */
function drush_user_password($inputs) {
  $userlist = new UserList($inputs);
  if (!drush_get_context('DRUSH_SIMULATE')) {
    $pass = drush_get_option('password');
    // If no password has been provided, prompt for one.
    if (empty($pass)) {
      $pass = drush_prompt(dt('Password'), NULL, TRUE, TRUE);
    }
    foreach ($userlist->accounts as $account) {
      $userlist->each('password', array($pass));
    }
    drush_log(dt('Changed password for !users', array('!users' => $userlist->names())), LogLevel::SUCCESS);
  }
}

/**
 * Displays a one time login link for the given user.
 */
function drush_user_login($inputs = '', $path = NULL) {
  $args = func_get_args();

  // Redispatch if called against a remote-host so a browser is started on the
  // the *local* machine.
  $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
  if (drush_sitealias_is_remote_site($alias)) {
    $return = drush_invoke_process($alias, 'user-login', $args, drush_redispatch_get_options(), array('integrate' => FALSE));
    if ($return['error_status']) {
      return drush_set_error('Unable to execute user login.');
    }
    else {
      // Prior versions of Drupal returned a string so cast to an array if needed.
      $links = is_string($return['object']) ? array($return['object']) : $return['object'];
    }
  }
  else {
    if (!drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      // Fail gracefully if unable to bootstrap Drupal.
      // drush_bootstrap() has already logged an error.
      return FALSE;
    }

    if (drush_get_option('uid', FALSE) || drush_get_option('name', FALSE) || drush_get_option('mail', FALSE)) {
      // If we only have a single argument and one of the user options is passed,
      // then we assume the argument is the path to open.
      if (count($args) == 1) {
        $path = $args[0];
      }
    }

    // Try to load a user from provided options and arguments.
    try {
      $userlist = new UserList($inputs);
    }
    catch (UserListException $e) {
      // No user option or argument was passed, so we default to uid 1.
        $userlist = new UserList(1);
    }
    $links = $userlist->each('passResetUrl', array($path));
  }
  $port = drush_get_option('redirect-port', FALSE);
  // There is almost always only one link so pick the first one for display and browser.
  // The full array is sent on backend calls.
  $first = current($links);
  drush_start_browser($first, FALSE, $port);
  drush_backend_set_result($links);
  return $first;
}
