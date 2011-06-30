<?php
// $Id: 

/**
 * @file Drush User Management commands
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
  $items['user-information'] = array(
    'callback' => 'drush_user_information',
    'description' => 'Print information about the specified user(s).',
    'aliases' => array('uinf'),
    'examples' => array(
      'drush user-information 2,3,someguy,somegal,billgates@microsoft.com' => 
        'Display information about any users with uids, names, or mail addresses matching the strings between commas.',
    ),
    'arguments' => array(
      'users' => 'A comma delimited list of uids, user names, or email addresses.',
    ),
    'options' => array(
      'full' => 'show extended information about the user',
      'short' => 'show basic information about the user (this is the default)',
    ),
  );
  $items['user-block'] = array(
    'callback' => 'drush_user_block',
    'description' => 'Block the specified user(s).',
    'aliases' => array('ublk'),
    'arguments' => array(
      'users' => 'A comma delimited list of uids, user names, or email addresses.',
    ),
    'examples' => array(
      'drush user-block 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' => 
        'Block the users with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => array(
      'uid' => 'A comma delimited list of uids to block',
      'name' => 'A comma delimited list of user names to block',
      'mail' => 'A comma delimited list of user mail addresses to block',
    ),
  );
  $items['user-unblock'] = array(
    'callback' => 'drush_user_unblock',
    'description' => 'Unblock the specified user(s).',
    'aliases' => array('uublk'),
    'arguments' => array(
      'users' => 'A comma delimited list of uids, user names, or email addresses.',
    ),
    'examples' => array(
      'drush user-unblock 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' => 
        'Unblock the users with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => array(
      'uid' => 'A comma delimited list of uids to unblock',
      'name' => 'A comma delimited list of user names to unblock',
      'mail' => 'A comma delimited list of user mail addresses to unblock',
    ),
  );
  $items['user-add-role'] = array(
    'callback' => 'drush_user_add_role',
    'description' => 'Add a role to the specified user accounts.',
    'aliases' => array('urol'),
    'arguments' => array(
      'role' => 'The name of the role to add',
      'users' => '(optional) A comma delimited list of uids, user names, or email addresses.',
    ),
    'examples' => array(
      'drush user-add-role "power user" 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' => 
        'Add the "power user" role to the accounts with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => array(
      'uid' => 'A comma delimited list of uids',
      'name' => 'A comma delimited list of user names',
      'mail' => 'A comma delimited list of user mail addresses',
    ),
  );
  $items['user-remove-role'] = array(
    'callback' => 'drush_user_remove_role',
    'description' => 'Remove a role from the specified user accounts.',
    'aliases' => array('urrol'),
    'arguments' => array(
      'role' => 'The name of the role to remove',
      'users' => '(optional) A comma delimited list of uids, user names, or email addresses.',
    ),
    'examples' => array(
      'drush user-remove-role "power user" 5,user3 --uid=2,3 --name=someguy,somegal --mail=billgates@microsoft.com' => 
        'Remove the "power user" role from the accounts with name, id, or email 5 or user3, uids 2 and 3, names someguy and somegal, and email address of billgates@microsoft.com',
    ),
    'options' => array(
      'uid' => 'A comma delimited list of uids',
      'name' => 'A comma delimited list of user names',
      'mail' => 'A comma delimited list of user mail addresses',
    ),
  );
  $items['user-create'] = array(
    'callback' => 'drush_user_create',
    'description' => 'Create a user account with the specified name.',
    'aliases' => array('ucrt'),
    'arguments' => array(
      'name' => 'The name of the account to add'
    ),
    'examples' => array(
      'drush user-create newuser --mail="person@example.com" --password="letmein"' => 
        'Create a new user account with the name newuser, the email address person@example.com, and the password letmein',
    ),
    'options' => array(
      'password' => 'The password for the new account',
      'mail' => 'The email address for the new account',
    ),
  );
  $items['user-cancel'] = array(
    'callback' => 'drush_user_cancel',
    'description' => 'Cancel a user account with the specified name.',
    'aliases' => array('ucan'),
    'arguments' => array(
      'name' => 'The name of the account to cancel',
    ),
    'examples' => array(
      'drush user-cancel username' => 
        'Cancel the user account with the name username and anonymize all content created by that user.',
    ),
  );
  $items['user-password'] = array(
    'callback' => 'drush_user_password',
    'description' => '(Re)Set the password for the user account with the specified name.',
    'aliases' => array('upwd'),
    'arguments' => array(
      'name' => 'The name of the account to modify'
    ),
    'options' => array(
      'password' => '(required) The new password for the account',
    ),
    'examples' => array(
      'drush user-password someuser --password="gr3@tP@$s"' => 
        'Set the password for the username someuser to gr3@tP@$s.',
    ),
  );
  $items['user-login'] = array(
    'callback' => 'drush_user_login',
    'description' => 'Display a one time login link for the given user account (defaults to uid 1).',
    'aliases' => array('uli'),
    'arguments' => array(
      'name' => 'The name of the account to log in as. Leave it empty to log in as uid 1.'
    ),
    'examples' => array(
      'drush user-login ryan' => 'Displays a one-time login link for the user ryan.',
      'open `drush user-login ryan`' => 'Open web browser and login as user ryan.',
    ),
  );

  // Drupal 7 only options.
  if (drush_drupal_major_version() >= 7) {
    $items['user-cancel']['options'] = array(
      'delete-content' => 'Delete all content created by the user',
    );
    $items['user-cancel']['examples']['drush user-cancel --delete-content username'] = 
      'Cancel the user account with the name username and delete all content created by that user.';
  }
  return $items;
}

// Implementation of hook_drush_init().
function user_drush_init() {
  $command_info = drush_get_command();
  $command = $command_info['command'];
  $needs_parse_args = array('user-block', 'user-unblock', 'user-add-role', 'user-remove-role');
  if (in_array($command, $needs_parse_args)) {
    // parse args and call drush_set_option for --uids
    $users = array();
    foreach (array('uid', 'name', 'mail' ) as $user_attr) {
      if ($arg = drush_get_option($user_attr)) {
        foreach(explode(',', $arg) as $search) {
          $uid_query = FALSE;
          switch ($user_attr) {
            case 'uid':
              if (drush_drupal_major_version() >= 7) {
                $uid_query = db_query("SELECT uid FROM {users} WHERE uid = :uid", array(':uid' => $search));
              }
              else {
                $uid_query = db_query("SELECT uid FROM {users} WHERE uid = %d", $search);
              }
              break;
            case 'name':
              if (drush_drupal_major_version() >= 7) {
                $uid_query = db_query("SELECT uid FROM {users} WHERE name = :name", array(':name' => $search));
              }
              else {
                $uid_query = db_query("SELECT uid FROM {users} WHERE name = '%s'", $search);
              }
              break;
            case 'mail':
              if (drush_drupal_major_version() >= 7) {
                $uid_query = db_query("SELECT uid FROM {users} WHERE mail = :mail", array(':mail' => $search));
              }
              else {
                $uid_query = db_query("SELECT uid FROM {users} WHERE mail = '%s'", $search);
              }
              break;
          }
          if ($uid_query !== FALSE) {
            if ($uid = drush_db_result($uid_query)) {
              $users[] = $uid;
            }
            else {
              drush_set_error("Could not find a uid for $user_attr = $search");
            }
          }
        }
      }
    }
    if (!empty($users)) {
      drush_set_option('uids', $users);
    }
  }
}

/**
 * Prints information about the specified user(s).
 */
function drush_user_information($users) {
  $users = explode(',', $users);
  foreach($users as $user) {
    $uid = _drush_user_get_uid($user);  
    if ($uid !== FALSE) {
        _drush_user_print_info($uid);
    }
  }
}

/**
 * Block the specified user(s).
 */
function drush_user_block($users = '') {
  $uids = drush_get_option('uids');
  if ($users !== '') {
    $users = explode(',', $users);
    foreach($users as $user) {
      $uid = _drush_user_get_uid($user);  
      if ($uid !== FALSE) {
        $uids[] = $uid;
      }
    }
  }
  if (!empty($uids)) {
    drush_op('user_user_operations_block', $uids);
  }
  else {
    return drush_set_error("Could not find any valid uids!");
  }
}

/**
 * Unblock the specified user(s).
 */
function drush_user_unblock($users = '') {
  $uids = drush_get_option('uids');
  if ($users !== '') {
    $users = explode(',', $users);
    foreach($users as $user) {
      $uid = _drush_user_get_uid($user);  
      if ($uid !== FALSE) {
        $uids[] = $uid;
      }
    }
  }
  if (!empty($uids)) {
    drush_op('user_user_operations_unblock', $uids);
  }
  else {
    return drush_set_error("Could not find any valid uids!");
  }
}

/**
 * Add a role to the specified user accounts.
 */
function drush_user_add_role($role, $users = '') {
  $uids = drush_get_option('uids');
  if ($users !== '') {
    $users = explode(',', $users);
    foreach($users as $user) {
      $uid = _drush_user_get_uid($user);  
      if ($uid !== FALSE) {
        $uids[] = $uid;
      }
    }
  }
  if (drush_drupal_major_version() >= 7) {
    $rid_query = db_query("SELECT rid FROM {role} WHERE name = :role", array(':role' => $role));
  }
  else {
    $rid_query = db_query("SELECT rid FROM {role} WHERE name = '%s'", $role);
  }
  if (!empty($uids)) {
    if ($rid = drush_db_result($rid_query)) {
      drush_op('user_multiple_role_edit', $uids, 'add_role', $rid);
      foreach($uids as $uid) {
        drush_log(dt("Added the %role role to uid %uid", array('%role' => $role, '%uid' => $uid)), 'success');
      }
    }
    else {
      return drush_set_error("There is no role named: \"$role\"!");
    }
  }
  else {
    return drush_set_error("Could not find any valid uids!");
  }
}

/**
 * Remove a role from the specified user accounts.
 */
function drush_user_remove_role($role, $users = '') {
  $uids = drush_get_option('uids');
  if ($users !== '') {
    $users = explode(',', $users);
    foreach($users as $user) {
      $uid = _drush_user_get_uid($user);  
      if ($uid !== FALSE) {
        $uids[] = $uid;
      }
    }
  }
  if (drush_drupal_major_version() >= 7) {
    $rid_query = db_query("SELECT rid FROM {role} WHERE name = :role", array(':role' => $role));
  }
  else {
    $rid_query = db_query("SELECT rid FROM {role} WHERE name = '%s'", $role);
  }
  if (!empty($uids)) {
    if ($rid = drush_db_result($rid_query)) {
      drush_op('user_multiple_role_edit', $uids, 'remove_role', $rid);
      foreach($uids as $uid) {
        drush_log(dt("Removed the %role role from uid %uid", array('%role' => $role, '%uid' => $uid)), 'success');
      }
    }
    else {
      return drush_set_error("There is no role named: \"$role\"!");
    }
  }
  else {
    return drush_set_error("Could not find any valid uids!");
  }
}

/**
 * Creates a new user account.
 */
function drush_user_create($name) {
  $mail = drush_get_option('mail');
  $pass = drush_get_option('password');
  $new_user = array(
    'name' => $name,
    'pass' => $pass,
    'mail' => $mail,
    'access' => '0',
    'status' => 1,
  );
  if (drush_drupal_major_version() >= 7) {
    $result = db_query("SELECT uid FROM {users} WHERE name = :name OR mail = :mail", array(':name' => $name, ':mail' => $new_user['mail']));
  }
  else {
    $result = db_query("SELECT uid FROM {users} WHERE name = '%s' OR mail = '%s'", $name, $new_user['mail']);
  }
  if (drush_db_result($result) === FALSE) {
    if (!drush_get_context('DRUSH_SIMULATE')) {
      $new_user_object = user_save(NULL, $new_user, NULL);
      if ($new_user_object !== FALSE) {
        _drush_user_print_info($new_user_object->uid);
        return $new_user_object->uid;
      }
      else {
        drush_set_error("Could not create a new user account with the name " . $name . "!");
      }
    }
  }
  else {
    drush_set_error("There is already a user account with the name " . $name . " or email address " . $new_user['mail'] . "!");
  }
}

/**
 * Cancels a user account.
 */
function drush_user_cancel($name) {
  if (drush_drupal_major_version() >= 7) {
    $result = db_query("SELECT uid FROM {users} WHERE name = :name", array(':name' => $name));
  }
  else {
    $result = db_query("SELECT uid FROM {users} WHERE name = '%s'", $name);
  }
  $uid = drush_db_result($result);
  if ($uid !== FALSE) {
    drush_print("Cancelling the user account with the following information:");
    _drush_user_print_info($uid);
    if (drush_get_option('delete-content') && drush_drupal_major_version() >= 7) {
      drush_print("All content created by this user will be deleted!");
    }
    if (drush_confirm('Cancel user account?: ')) {
      if (drush_drupal_major_version() >= 7) {
        if (drush_get_option('delete-content')) {
          user_cancel(array(), $uid, 'user_cancel_delete');
        }
        else {
          user_cancel(array(), $uid, 'user_cancel_reassign');
        }
        // I got the following technique here: http://drupal.org/node/638712
        $batch =& batch_get();
        $batch['progressive'] = FALSE;
        batch_process();
      }
      else {
        user_delete(array(), $uid);
      }
    }
  }
  else {
    drush_set_error("Could not find a user account with the name " . $name . "!");
  }
}

/**
 * Sets the password for the account with the given username
 */
function drush_user_password($name) {
  $pass = drush_get_option('password');
  if (empty($pass)) {
    return drush_set_error("You must specify a password!");
  }
  if (drush_drupal_major_version() >= 7) {
    $user = user_load_by_name($name);
  }
  else {
    $user = user_load(array('name' => $name));
  }
  if ($user !== FALSE) {
    if (!drush_get_context('DRUSH_SIMULATE')) {
      $user_object = user_save($user, array('pass' => $pass));
      if ($user_object === FALSE) {
        drush_set_error("Could not change the password for the user account with the name " . $name . "!");
      }
    }
  }
  else {
    drush_set_error("The user account with the name " . $name . " could not be loaded!");
  }
}

/**
 * Displays a one time login link for the given user.
 */
function drush_user_login($name = NULL) {
  if (empty($name)) {
    $user = user_load(1);
    $name = '[uid 1]';
  }
  elseif (drush_drupal_major_version() >= 7) {
    $user = user_load_by_name($name);
  }
  else {
    $user = user_load(array('name' => $name));
  }

  if ($user !== FALSE) {
    $link = user_pass_reset_url($user);
    drush_print($link);
    return $link;
  }
  else {
    drush_set_error("The user account with the name " . $name . " could not be loaded!");
  }
}

/**
 * Print information about a given uid
 */
function _drush_user_print_info($uid) {
  if (drush_drupal_major_version() >= 7) {
    $userinfo = user_load($uid);
  }
  else {
    $userinfo = user_load(array('uid' => $uid));
  }
  if (drush_get_option('full')) {
    $userinfo = (array)$userinfo;
    $userinfo_pipe = array();
    unset($userinfo['data']);
    unset($userinfo['block']);
    unset($userinfo['form_build_id']);
    foreach($userinfo as $key => $val) {
      if (is_array($val)) {
        drush_print($key . ': ');
        drush_print_r($val);
        $userinfo_pipe[] = '"' . implode(",", $val) . '"';
      }
      else {
        if ($key === 'created' OR $key === 'access' OR $key === 'login') {
          drush_print($key . ': ' . format_date($val));
          $userinfo_pipe[] = $val;
        }
        else {
          drush_print($key . ': ' . $val);
          $userinfo_pipe[] = $val;
        }
      }
    }
    drush_print_pipe(implode(",", $userinfo_pipe));
    drush_print_pipe("\n");
  }
  else {
    $userinfo_short = array(
      'User ID' => $userinfo->uid,
      'User name' => $userinfo->name,
      'User mail' => $userinfo->mail,
    );
    $userinfo_short['User roles'] = implode(', ', $userinfo->roles);
    $userinfo->status ? $userinfo_short['User status'] = 'active' : $userinfo_short['User status'] = 'blocked';
    drush_print_table(drush_key_value_to_array_table($userinfo_short));
    drush_print_pipe("$userinfo->name, $userinfo->uid, $userinfo->mail, $userinfo->status, \"" . implode(', ', $userinfo->roles) . "\"\n");
  }
}

/**
 * Get uid(s) from a uid, user name, or email address.
 * Returns a uid, or FALSE if none found.
 */
function _drush_user_get_uid($search) {
  // We use a DB query while looking for the uid to keep things speedy.
  $uids = array();
  if (is_numeric($search)) {
    if (drush_drupal_major_version() >= 7) {
      $uid_query = db_query("SELECT uid, name FROM {users} WHERE uid = :uid OR name = :name", array(':uid' => $search, ':name' => $search));
    }
    else {
      $uid_query = db_query("SELECT uid, name FROM {users} WHERE uid = %d OR name = '%d'", $search, $search);
    }
  }
  else {
    if (drush_drupal_major_version() >= 7) {
      $uid_query = db_query("SELECT uid, name FROM {users} WHERE mail = :mail OR name = :name", array(':mail' => $search, ':name' => $search));
    }
    else {
      $uid_query = db_query("SELECT uid, name FROM {users} WHERE mail = '%s' OR name = '%s'", $search, $search);
    }
  }
  while ($uid = drush_db_fetch_object($uid_query)) {
    $uids[$uid->uid] = $uid->name;
  }
  switch (count($uids)) {
    case 0:
      return drush_set_error("Could not find a uid for the search term '" . $search . "'!");
      break;
    case 1:
      return array_pop(array_keys($uids));
      break;
    default:
      drush_print('More than one user account was found for the search string "' . $search . '".');
      return(drush_choice($uids, 'Please choose a name:', '!value (uid=!key)'));
  }
}
