<?php
namespace Drush\Commands\core;

use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

class LoginCommands extends DrushCommands
{

    /**
     * Display a one time login link for user ID 1, or another user.
     *
     * @command user-login
     *
     * @param string $user  An optional uid, email address or user name for the user to log in as.
     *   Default is to log in as uid 1. The uid/mail/name options take priority if specified.
     *   If the $path argument is omitted, and one of the uid, name, or email options is specified, $user will be treated as the $path.
     * @param string $path  Optional path to redirect to after logging in.
     * @option uid A user id to log in as.  If specified, takes priority over $user argument and other options.
     * @option mail A user email to log in as.  Takes priority after uid option.
     * @option name A user name to log in as.  Takes priority after uid and mail options.
     * @option browser Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser.
     * @option redirect-port A custom port for redirecting to (e.g., when running within a Vagrant environment)
     * @bootstrap DRUSH_BOOTSTRAP_NONE
     * @handle-remote-commands
     * @aliases uli
     * @usage drush user-login
     *   Open default web browser and browse to homepage, logged in as uid=1.
     * @usage drush user-login --name=ryan node/add/blog
     *   Open default web browser (if configured or detected) for a one-time login link for username ryan that redirects to node/add/blog.
     * @usage drush user-login --browser=firefox --mail=drush@example.org
     *   Open firefox web browser, and login as the user with the e-mail address drush@example.org.
     */
    public function login($user = '', $path = '', $options = ['uid' => '', 'name' => '', 'mail' => '', 'browser' => '', 'redirect-port' => '']) {

      // Redispatch if called against a remote-host so a browser is started on the
      // the *local* machine.
      $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
      if (drush_sitealias_is_remote_site($alias)) {
        $return = drush_invoke_process($alias, 'user-login', [$user, $path], drush_redispatch_get_options(), ['integrate' => FALSE]);
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

        // Convenience variables.
        $account = $uid = $name = $mail = NULL;
        $user_as_path = FALSE;

        // Has the user attempted to specify a target user through options?
        $has_user_option = (!empty($options['uid']) || !empty($options['name']) || !empty($options['mail']));

        if ($has_user_option) {

          // Treat the first argument as $path if $path is empty.
          if (empty($path)) {
            $path = $user;
            $user = NULL;
          }

          // Grab all of the options so that user will still load if uid is bad but email is correct.
          // Again, mimicing behavior of drush 8.
          if (!empty($options['uid'])) {
            $uid = intval($options['uid']);
          }
          if (!empty($options['name'])) {
            $name = $options['name'];
          }
          if (!empty($options['mail'])) {
            $mail = $options['mail'];
          }
        }
        // If no user-specifying option is present, then try to parse the $user argument.
        else {
          // Integers are treated as uids, true even if path not specified.
          if (ctype_digit($user)) {
            $uid = intval($user);
          }
          // Strings that look like email addresses are treated as mail, even if path not specified.
          elseif (\Drupal::service('email.validator')->isValid($user)) {
            $mail = $user;
          }
          // Anything else.
          else {
            $name = $user;
            // Flag to set path in case $name turns out not to be valid.
            if (empty($path)) {
              $user_as_path = TRUE;
            }

          }
        }

        // Attempt to load the user with the appropriate method.
        // Cascade the attempts, so that if account is not loaded by prior attempt, others are attempted.
        // This mimics behavior in drush 8.
        if ($uid && $account = User::load($uid)) {}
        elseif ($mail && $account = user_load_by_mail($mail)) {}
        elseif ($name && $account = user_load_by_name($name)) {}
        else {
          $uid = 1;
          $account = User::load($uid);
          if ($name && $user_as_path) {
            $path = $name;
            $name = NULL;
          }
        }

        // Loading of specified account failed, throw exception.
        if (!$account) {
          throw new \Exception(dt('Unable to find a matching user. !uid!mail!name', [
            '!uid' => ($uid ? 'User id ' . $uid : ''),
            '!mail' => ($mail ? 'User email ' . $mail : ''),
            '!name' => ($name ? 'User name ' . $name : ''),
          ]));
        }

        $link = user_pass_reset_url($account) . '/login';
        if ($path) {
          $link .= '?destination=' . $path;
        }
      }
      $port = drush_get_option('redirect-port', false);
      drush_start_browser($link, false, $port);
      // Use an array for backwards compat.
      drush_backend_set_result([$link]);
      return $link;
    }
}
