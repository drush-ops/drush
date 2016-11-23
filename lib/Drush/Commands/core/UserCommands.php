<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;
use Drush\User\UserList;

class UserCommands extends DrushCommands {

  /**
   * Print information about the specified user(s).
   *
   * @command user-information
   *
   * @param string $names A comma delimited list of user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases uinf
   * @usage drush user-information someguy,somegal
   *   Display information about the someguy and somegal user accounts.
   * @field-labels
   *   uid: User ID
   *   name: User name
   *   pass: Password
   *   mail: User mail
   *   theme: User theme
   *   signature: Signature
   *   signature_format: Signature format
   *   user_created: User created
   *   created: Created
   *   user_access: User last access
   *   access: Last access
   *   user_login: User last login
   *   login: Last login
   *   user_status: User status
   *   status: Status
   *   timezone: Time zone
   *   picture: User picture
   *   init: Initial user mail
   *   roles: User roles
   *   group_audience: Group Audience
   *   langcode: Language code
   *   uuid: Uuid
   * @table-style default
   * @default-fields uid,name,mail,user_status
   * @todo --pipe not showing csv
   * @pipe-format csv
   * // @todo csv the roles cell with a renderer. also add to fields-default
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function information($names = '', $options = ['format' => 'table', 'fields' => '']) {
    $userlist = new UserList($names);
    $info = $userlist->each('info');
    return new RowsOfFields($info);
  }

  /**
   * Block the specified user(s).
   *
   * @command user-block
   *
   * @param string $names A comma delimited list of user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases ublk
   * @usage drush user-block user3
   *   Block the users whose name is user3
   */
  public function block($names = '') {
    $userlist = new UserList($names);
    $userlist->each('block');
    $this->logger->log(LogLevel::SUCCESS, dt('Blocked user(s): !users', array(
      '!users' => $userlist->names(),
    )));
  }

  /**
   * UnBlock the specified user(s).
   *
   * @command user-unblock
   *
   * @param string $names A comma delimited list of user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases uublk
   * @usage drush user-unblock user3
   *   Unblock the users with name user3
   */
  public function unblock($names = '') {
    $userlist = new UserList($names);
    $userlist->each('unblock');
    $this->logger->log(LogLevel::SUCCESS, dt('Unblocked user(s): !users', array(
      '!users' => $userlist->names(),
    )));
  }

  /**
   * Add a role to the specified user accounts.
   *
   * @command user-add-role
   *
   * @param string $role The name of the role to add
   * @param string $names A comma delimited list user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases urol
   * @complete \Drush\Commands\core\UserCommands::complete
   * @usage drush user-add-role "power user" user3
   *   Add the "power user" role to user3
   */
  public function addRole($role, $names = '') {
    // If role is not found, an exception gets thrown and handled by command invoke.
    $role_object = drush_role_get_class($role);
    $userlist = new UserList($names);
    $userlist->each('addRole', array($role_object->rid));
    $this->logger->log(LogLevel::SUCCESS, dt('Added !role role to !users', array(
      '!users' => $userlist->names(),
    )));
  }

  /**
   * Remove a role from the specified user accounts.
   *
   * @command user-remove-role
   *
   * @param string $role The name of the role to add
   * @param string $names A comma delimited list of user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases urrol
   * @complete \Drush\Commands\core\UserCommands::complete
   * @usage drush user-remove-role "power user" user3
   *   Remove the "power user" role from user3
   */
  public function removeRole($role, $names = '') {
    // If role is not found, an exception gets thrown and handled by command invoke.
    $role_object = drush_role_get_class($role);
    $userlist = new UserList($names);
    $userlist->each('removeRole', array($role_object->rid));
    $this->logger->log(LogLevel::SUCCESS, dt('Removed user(s): !users', array(
      '!users' => $userlist->names(),
    )));
  }

  /**
   * Create a user account.
   *
   * @command user-create
   *
   * @param string $name The name of the account to add
   * @option string password The password for the new account
   * @option string mail The email address for the new account
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases ucrt
   * @usage drush user-create newuser --mail="person@example.com" --password="letmein"
   *   Create a new user account with the name newuser, the email address person@example.com, and the password letmein
   */
  public function create($name, $options = ['password' => '', 'mail' => '']) {
    $userversion = drush_user_get_class();
    $new_user = array(
      'name' => $name,
      'pass' => $options['password'],
      'mail' => $options['mail'],
      'access' => '0',
      'status' => 1,
    );
    if (!drush_get_context('DRUSH_SIMULATE')) {
      if ($account = $userversion->create($new_user)) {
        drush_backend_set_result($account->info());
        $this->logger()->log(LogLevel::SUCCESS, dt('Created a new user with uid !uid', array('!uid' => $account->id())));
      }
      else {
        return new CommandError("Could not create a new user account with the name " . $name . ".");
      }
    }
  }

  /**
   * Assure that provided username is available.
   *
   * @hook validate user-create
   */
  public function createValidate(CommandData $commandData) {
    $userversion = drush_user_get_class();
    if ($mail = $commandData->input()->getOption('mail')) {
      if ($userversion->load_by_mail($mail)) {
        throw new \Exception(dt('There is already a user account with the email !mail', array('!mail' => $mail)));
      }
    }
    $name = $commandData->input()->getArgument('name');
    if ($userversion->load_by_name($name)) {
      throw new \Exception((dt('There is already a user account with the name !name', array('!name' => $name))));
    }
  }

  /**
   * Cancel user account(s) with the specified name(s).
   *
   * @command user-cancel
   *
   * @param string $names A comma delimited list of user names.
   * @option bool delete-content Delete all content created by the user
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases ucan
   * @usage drush user-cancel username
   *   Cancel the user account with the name username and anonymize all content created by that user.
   * @usage drush user-cancel --delete-content username
   *   Cancel the user account with the name username and delete all content created by that user.
   */
  public function cancel($names, $options = ['delete-account' => FALSE]) {
    $userlist = new UserList($names);
    foreach ($userlist->accounts as $account) {
      if ($options['delete-content'] && drush_drupal_major_version() >= 7) {
        $this->logger()->log(LogLevel::OK, dt('All content created by !name will be deleted.', array('!name' => $account->getUsername())));
      }
      if (drush_confirm('Cancel user account?: ')) {
        $account->cancel();
      }
    }
    $this->logger()->log(LogLevel::SUCCESS, dt('Cancelled user(s): !users', array('!users' => $userlist->names())));
  }

  /**
   * Set the password for the user account with the specified name.
   *
   * @command user-password
   *
   * @param string $name The name of the account to modify.
   * @param string $password The new password for the account.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases upwd
   * @usage drush user-password someuser "correct horse battery staple"
   *   Set the password for the username someuser. @see xkcd.com/936
   */
  public function password($name, $password, $options = ['password' => '']) {
    $userlist = new UserList($name);
    if (!drush_get_context('DRUSH_SIMULATE')) {
      foreach ($userlist->accounts as $account) {
        $userlist->each('password', array($password));
      }
      $this->logger()->log(LogLevel::SUCCESS, dt('Changed password for !name.', array('!name' => $userlist->names())));
    }
  }

  /**
   * Display a one time login link for the given user account (defaults to uid 1).
   *
   * @command user-login
   *
   * @param string $name A user name to log in as. Defaults to uid=1
   * @param string $path Optional path to redirect to after logging in.
   * @option string browser Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser.
   * @option string redirect-port A custom port for redirecting to (e.g. when running within a Vagrant environment)
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @handle-remote-commands
   * @aliases uli
   * @usage drush user-login ryan node/add/blog
   *   Displays and opens default web browser (if configured or detected) for a one-time login link for the user with the username ryan and redirect to the path node/add/blog.
   * @usage drush user-login --browser=firefox --mail=drush@example.org admin/settings/performance
   *   Open firefox web browser, login as the user with the e-mail address drush@example.org and redirect to the path admin/settings/performance.
   */
  public function login($name = '1', $path = '', $options = ['browser' => '', 'redirect-port' => '']) {

    // Redispatch if called against a remote-host so a browser is started on the
    // the *local* machine.
    $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
    if (drush_sitealias_is_remote_site($alias)) {
      $return = drush_invoke_process($alias, 'user-login', $name, drush_redispatch_get_options(), array('integrate' => FALSE));
      if ($return['error_status']) {
        throw new \Exception('Unable to execute user login.');
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

      $userlist = new UserList($name);
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

  /**
   * A completion callback.
   *
   * @return array
   *   An array of available roles.
   */
  static function complete() {
    drush_bootstrap_max();
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    unset($roles['anonymous']);
    return array('values' => array_keys($roles));
  }

}
