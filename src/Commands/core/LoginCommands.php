<?php
namespace Drush\Commands\core;

use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

class LoginCommands extends DrushCommands {

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

}
