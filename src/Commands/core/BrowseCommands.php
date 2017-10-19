<?php
namespace Drush\Commands\core;

use Drupal\Core\Url;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

class BrowseCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use ExecTrait;
    use SiteAliasManagerAwareTrait;

    /**
     * Display a link to a given path or open link in a browser.
     *
     * @command browse
     *
     * @param string|null $path Path to open. If omitted, the site front page will be opened.
     * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
     * @option string $browser Specify a particular browser (defaults to operating system default). Use --no-browser to suppress opening a browser.
     * @option integer $redirect-port The port that the web server is redirected to (e.g. when running within a Vagrant environment).
     * @usage drush browse
     *   Open default web browser (if configured or detected) to the site front page.
     * @usage drush browse node/1
     *   Open web browser to the path node/1.
     * @usage drush @example.prod
     *   Open a browser to the web site specified in a site alias.
     * @usage drush browse --browser=firefox admin
     *   Open Firefox web browser to the path 'admin'.
     * @handle-remote-commands true
     */
    public function browse($path = '', array $options = ['browser' => self::REQ, 'redirect-port' => self::REQ])
    {
        $aliasRecord = $this->siteAliasManager()->getSelf();
        // Redispatch if called against a remote-host so a browser is started on the
        // the *local* machine.
        if ($aliasRecord->isRemote()) {
            $return = drush_invoke_process($aliasRecord, 'browse', [$path], Drush::redispatchOptions(), array('integrate' => true));
            if ($return['error_status']) {
                throw new \Exception('Unable to execute browse command on remote alias.');
            } else {
                $link = $return['object'];
            }
        } else {
            if (!drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                // Fail gracefully if unable to bootstrap Drupal. drush_bootstrap() has
                // already logged an error.
                return false;
            }
            $link = Url::fromUserInput('/' . $path, ['absolute' => true])->toString();
        }

        $this->startBrowser($link, false, $options['redirect-port']);
        return $link;
    }
}
