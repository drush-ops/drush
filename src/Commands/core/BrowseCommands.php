<?php
namespace Drush\Commands\core;

use Drupal\Core\Url;
use Drush\Commands\DrushCommands;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

class BrowseCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Display a link to a given path or open link in a browser.
     *
     * @command browse
     *
     * @param string|null $path Path to open. If omitted, the site front page will be opened.
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
     * @complete \Drush\Commands\core\BrowseCommands::complete
     * @handle-remote-commands true
     */
    public function browse($path = '', $options = ['browser' => null])
    {
        // TODO: Remove 2nd branch when no longer needed.
        if ($this->hasSiteAliasManager()) {
            $aliasRecord = $this->siteAliasManager()->getSelf();
            $is_remote = $aliasRecord->isRemote();
            $site_record = $aliasRecord->legacyRecord();
        } else {
            $alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS');
            $is_remote = drush_sitealias_is_remote_site($alias);
            $site_record = drush_sitealias_get_record($alias);
        }
        // Redispatch if called against a remote-host so a browser is started on the
        // the *local* machine.
        if ($is_remote) {
            $return = drush_invoke_process($site_record, 'browse', [$path], drush_redispatch_get_options(), array('integrate' => true));
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

        drush_start_browser($link);
        return $link;
    }

    /*
     * An argument provider for shell completion.
     */
    public static function complete()
    {
        return ['values' => ['admin', 'admin/content', 'admin/reports', 'admin/structure', 'admin/people', 'admin/modules', 'admin/config']];
    }
}
