<?php
namespace Drush\Commands\core;

use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

class LoginCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{

    use SiteAliasManagerAwareTrait;
    use ExecTrait;

    /**
     * Display a one time login link for user ID 1, or another user.
     *
     * @command user:login
     *
     * @param string $path Optional path to redirect to after logging in.
     * @option name A user name to log in as. If not provided, defaults to uid=1.
     * @option browser Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser.
     * @option redirect-port A custom port for redirecting to (e.g., when running within a Vagrant environment)
     * @bootstrap none
     * @handle-remote-commands
     * @aliases uli,user-login
     * @usage drush user:login
     *   Open default web browser and browse to homepage, logged in as uid=1.
     * @usage drush user:login --name=ryan node/add/blog
     *   Open default web browser (if configured or detected) for a one-time login link for username ryan that redirects to node/add/blog.
     * @usage drush user:login --browser=firefox --mail=drush@example.org
     *   Open firefox web browser, and login as the user with the e-mail address drush@example.org.
     */
    public function login($path = '', $options = ['name' => '1', 'browser' => true, 'redirect-port' => self::REQ])
    {

        // Redispatch if called against a remote-host so a browser is started on the
        // the *local* machine.
        $aliasRecord = $this->siteAliasManager()->getSelf();
        if ($aliasRecord->isRemote()) {
            $return = drush_invoke_process($aliasRecord, 'user-login', [$path], Drush::redispatchOptions(), ['integrate' => false]);
            if ($return['error_status']) {
                throw new \Exception('Unable to execute user login.');
            } else {
                $link = is_string($return['object']) ?: current($return['object']);
            }
        } else {
            if (!Drush::bootstrapManager()->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                throw new \Exception(dt('Unable to bootstrap Drupal.'));
            }

            if ($options['name'] == 1) {
                $account = User::load(1);
            } elseif (!$account = user_load_by_name($options['name'])) {
                throw new \Exception(dt('Unable to load user: !user', ['!user' => $options['name']]));
            }
            $link = user_pass_reset_url($account). '/login';
            if ($path) {
                $link .= '?destination=' . $path;
            }
        }
        $port = $options['redirect-port'];
        $this->startBrowser($link, false, $port, $options['browser']);
        // Use an array for backwards compat.
        drush_backend_set_result([$link]);
        return $link;
    }
}
