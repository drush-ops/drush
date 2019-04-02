<?php
namespace Drush\Commands\core;

use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

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
     * @option name A user name to log in as.
     * @option uid A uid to log in as.
     * @option mail A user mail address to log in as.
     * @option browser Optional value denotes which browser to use (defaults to operating system default). Use --no-browser to suppress opening a browser.
     * @option redirect-port A custom port for redirecting to (e.g., when running within a Vagrant environment)
     * @bootstrap none
     * @handle-remote-commands
     * @aliases uli,user-login
     * @usage drush user:login
     *   Open default web browser and browse to homepage, logged in as uid=1.
     * @usage drush user:login --name=ryan node/add/blog
     *   Open default web browser (if configured or detected) for a one-time login link for username ryan that redirects to node/add/blog.
     * @usage drush user:login --uid=123
     *   Open default web browser and login as user with uid "123".
     * @usage drush user:login --mail=foo@bar.com
     *   Open default web browser and login as user with mail "foo@bar.com".
     * @usage drush user:login --browser=firefox --name=$(drush user:information --mail="drush@example.org" --fields=name --format=string)
     *   Open firefox web browser, and login as the user with the e-mail address drush@example.org.
     */
    public function login($path = '', $options = ['name' => null, 'uid' => null, 'mail' => null, 'browser' => true, 'redirect-port' => self::REQ])
    {

        // Redispatch if called against a remote-host so a browser is started on the
        // the *local* machine.
        $aliasRecord = $this->siteAliasManager()->getSelf();
        if ($this->processManager()->hasTransport($aliasRecord)) {
            $process = $this->processManager()->drush($aliasRecord, 'user-login', [$path], Drush::redispatchOptions());
            $process->mustRun();
            $link = $process->getOutput();
        } else {
            if (!Drush::bootstrapManager()->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                throw new \Exception(dt('Unable to bootstrap Drupal.'));
            }

            $account = null;
            if (!is_null($options['name']) && !$account = user_load_by_name($options['name'])) {
                throw new \Exception(dt('Unable to load user by name: !name', ['!name' => $options['name']]));
            }

            if (!is_null($options['uid']) && !$account = User::load($options['uid'])) {
                throw new \Exception(dt('Unable to load user by uid: !uid', ['!uid' => $options['uid']]));
            }

            if (!is_null($options['mail']) && !$account = user_load_by_mail($options['mail'])) {
                throw new \Exception(dt('Unable to load user by mail: !mail', ['!mail' => $options['mail']]));
            }

            if (empty($account)) {
                $account = User::load(1);
            }

            $link = user_pass_reset_url($account). '/login';
            if ($path) {
                $link .= '?destination=' . $path;
            }
        }
        $port = $options['redirect-port'];
        $this->startBrowser($link, false, $port, $options['browser']);
        // Use an array for backwards compat. Going forward, please expect a string.
        drush_backend_set_result([$link]);
        return $link;
    }
}
