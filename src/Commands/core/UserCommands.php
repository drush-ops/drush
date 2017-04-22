<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

class UserCommands extends DrushCommands {

  /**
   * Print information about the specified user(s).
   *
   * @command user-information
   *
   * @param string $names A comma delimited list of user names.
   * @option $uid A comma delimited list of user ids to lookup (an alternative to names).
   * @option $mail A comma delimited list of emails to lookup (an alternative to names).
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases uinf
   * @usage drush user-information someguy,somegal
   *   Display information about the someguy and somegal user accounts.
   * @usage drush user-information --mail=someguy@somegal.com
   *   Display information for a given email account.
   * @usage drush user-information --uid=5
   *   Display information for a given user id.
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
   * @default-fields uid,name,mail,roles,user_status
   * @todo --pipe showing a table, not csv.
   * @pipe-format csv
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function information($names = '', $options = ['format' => 'table', 'uid' => '', 'mail' => '', 'fields' => '']) {
    $accounts = [];
    if ($mails = _convert_csv_to_array($options['mail'])) {
      foreach ($mails as $mail) {
        if ($account = user_load_by_mail($mail)) {
          $accounts[$account->id()] = $account;
        }
      }
    }
    if ($uids = _convert_csv_to_array($options['uid'])) {
      if ($loaded = User::loadMultiple($uids)) {
        $accounts += $loaded;
      }
    }
    if ($names = _convert_csv_to_array($names)) {
      foreach ($names as $name) {
        if ($account = user_load_by_name($name)) {
          $accounts[$account->id()] = $account;
        }
      }
    }
    if (empty($accounts)) {
      throw new \Exception(dt('Unable to find a matching user'));
    }

    foreach ($accounts as $id => $account) {
      $outputs[$id] = $this->info_array($account);
    }

    $result = new RowsOfFields($outputs);
    $result->addRendererFunction([$this, 'renderRolesCell']);
    return $result;
  }

  public function renderRolesCell($key, $cellData, FormatterOptions $options)
  {
    if (is_array($cellData)) {
      return implode("\n", $cellData);
    }
    return $cellData;
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
  public function block($names) {
    if ($names = _convert_csv_to_array($names)) {
      foreach ($names as $name) {
        if ($account = user_load_by_name($name)) {
          $account->block();
          $account->save();
          $this->logger->success(dt('Blocked user(s): !user', array('!user' => $name)));
        }
        else {
          $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
        }
      }
    }
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
  public function unblock($names) {
    if ($names = _convert_csv_to_array($names)) {
      foreach ($names as $name) {
        if ($account = user_load_by_name($name)) {
          $account->activate();
          $account->save();
          $this->logger->success(dt('Unblocked user(s): !user', array('!user' => $name)));
        }
        else {
          $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
        }
      }
    }
  }

  /**
   * Add a role to the specified user accounts.
   *
   * @command user-add-role
   *
   * @validate-entity-load user_role role
   * @param string $role The name of the role to add
   * @param string $names A comma delimited list user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases urol
   * @complete \Drush\Commands\core\UserCommands::complete
   * @usage drush user-add-role "power user" user3
   *   Add the "power user" role to user3
   */
  public function addRole($role, $names) {
    if ($names = _convert_csv_to_array($names)) {
      foreach ($names as $name) {
        if ($account = user_load_by_name($name)) {
          $account->addRole($role);
          $account->save();
          $this->logger->success(dt('Added !role role to !user', array(
            '!role' => $role,
            '!user' => $name,
          )));
        }
        else {
          $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
        }
      }
    }
  }

  /**
   * Remove a role from the specified user accounts.
   *
   * @command user-remove-role
   *
   * @validate-entity-load user_role role
   * @param string $role The name of the role to add
   * @param string $names A comma delimited list of user names.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases urrol
   * @complete \Drush\Commands\core\UserCommands::complete
   * @usage drush user-remove-role "power user" user3
   *   Remove the "power user" role from user3
   */
  public function removeRole($role, $names) {
    if ($names = _convert_csv_to_array($names)) {
      foreach ($names as $name) {
        if ($account = user_load_by_name($name)) {
          $account->removeRole($role);
          $account->save();
          $this->logger->success(dt('Removed !role role from !user', array(
            '!role' => $role,
            '!user' => $name,
          )));
        }
        else {
          $this->logger->warning(dt('Unable to load user: !user', array('!user' => $name)));
        }
      }
    }
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
    $new_user = array(
      'name' => $name,
      'pass' => $options['password'],
      'mail' => $options['mail'],
      'access' => '0',
      'status' => 1,
    );
    if (!drush_get_context('DRUSH_SIMULATE')) {
      if ($account = User::create($new_user)) {
        $account->save();
        drush_backend_set_result($this->info_array($account));
        $this->logger()->success(dt('Created a new user with uid !uid', array('!uid' => $account->id())));
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
    if ($mail = $commandData->input()->getOption('mail')) {
      if (user_load_by_mail($mail)) {
        throw new \Exception(dt('There is already a user account with the email !mail', array('!mail' => $mail)));
      }
    }
    $name = $commandData->input()->getArgument('name');
    if (user_load_by_name($name)) {
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
    if ($names = _convert_csv_to_array($names)) {
      foreach ($names as $name) {
        if ($account = user_load_by_name($name)) {
          if ($options['delete-content']) {
            $this->logger()->warning(dt('All content created by !name will be deleted.', array('!name' => $account->getUsername())));
          }
          if ($this->io()->confirm('Cancel user account?: ')) {
            $account->cancel();
            $this->logger()->success(dt('Cancelled user: !user', array('!user' => $name)));
          }

        }
        else {
          $this->logger()->warning(dt('Unable to load user: !user', array('!user' => $name)));
        }
      }
    }
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
  public function password($name, $password) {
    if ($account = user_load_by_name($name)) {
      if (!drush_get_context('DRUSH_SIMULATE')) {
        $account->setpassword($password);
        $account->save();
        $this->logger()
          ->success(dt('Changed password for !name.', array('!name' => $name)));
      }
    }
    else {
      throw new \Exception(dt('Unable to load user: !user', array('!user' => $name)));
    }
  }

  /**
   * Display a one time login link for user ID 1, or another user.
   *
   * @command user-login
   *
   * @param string $path Optional path to redirect to after logging in.
   * @option name A user name to log in as. If not provided, defaults to uid=1.
   * @option browser Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser.
   * @option redirect-port A custom port for redirecting to (e.g., when running within a Vagrant environment)
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @handle-remote-commands
   * @aliases uli
   * @usage drush user-login --name=ryan node/add/blog
   *   Displays and opens default web browser (if configured or detected) for a one-time login link for the user with the username ryan and redirect to the path node/add/blog.
   * @usage drush user-login --browser=firefox --mail=drush@example.org admin/settings/performance
   *   Open firefox web browser, login as the user with the e-mail address drush@example.org and redirect to the path admin/settings/performance.
   */
  public function login($path = '', $options = ['name' => '1', 'browser' => '', 'redirect-port' => '']) {

    // Redispatch if called against a remote-host so a browser is started on the
    // the *local* machine.
    $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
    if (drush_sitealias_is_remote_site($alias)) {
      $return = drush_invoke_process($alias, 'user-login', $name, drush_redispatch_get_options(), array('integrate' => FALSE));
      if ($return['error_status']) {
        throw new \Exception('Unable to execute user login.');
      }
      else {
        $link = is_string($return['object']) ?: current($return['object']);
      }
    }
    else {
      if (!drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
        // Fail gracefully if unable to bootstrap Drupal.
        // drush_bootstrap() has already logged an error.
        return FALSE;
      }

      if ($options['name'] == 1) {
        $account = User::load(1);
      }
      elseif (!$account = user_load_by_name($options['name'])) {
        throw new \Exception(dt('Unable to load user: !user', array('!user' => $options['name'])));
      }
      $link = user_pass_reset_url($account). '/login';
      if ($path) {
        $link .= '?destination=' . $path;
      }
    }
    $port = drush_get_option('redirect-port', FALSE);
    drush_start_browser($link, FALSE, $port);
    // Use an array for backwards compat.
    drush_backend_set_result([$link]);
    return $link;
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

  /**
   * A flatter and simpler array presentation of a Drupal $user object.
   *
   * @param $account A user account
   * @return array
   */
  public function info_array($account) {
    return array(
      'uid' => $account->id(),
      'name' => $account->getUsername(),
      'password' => $account->getPassword(),
      'mail' => $account->getEmail(),
      'user_created' => $account->getCreatedTime(),
      'created' => format_date($account->getCreatedTime()),
      'user_access' => $account->getLastAccessedTime(),
      'access' => format_date($account->getLastAccessedTime()),
      'user_login' => $account->getLastLoginTime(),
      'login' => format_date($account->getLastLoginTime()),
      'user_status' => $account->get('status')->value,
      'status' => $account->isActive() ? 'active' : 'blocked',
      'timezone' => $account->getTimeZone(),
      'roles' => $account->getRoles(),
      'langcode' => $account->getPreferredLangcode(),
      'uuid' => $account->uuid->value,
    );
  }
}
