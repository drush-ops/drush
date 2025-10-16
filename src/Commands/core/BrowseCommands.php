<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;

final class BrowseCommands extends DrushCommands
{
    use AutowireTrait;
    use ExecTrait;

    const BROWSE = 'browse';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Display a link to a given path or open link in a browser.
     */
    #[CLI\Command(name: self::BROWSE)]
    #[CLI\Argument(name: 'path', description: 'Path to open. If omitted, the site front page will be opened.')]
    #[CLI\Option(name: 'browser', description: 'Open the URL in the default browser. Use --no-browser to avoid opening a browser.')]
    #[CLI\Option(name: 'redirect-port', description: 'The port that the web server is redirected to (e.g. when running within a Vagrant environment).')]
    #[CLI\Usage(name: 'drush browse', description: 'Open default web browser (if configured or detected) to the site front page.')]
    #[CLI\Usage(name: 'drush browse node/1', description: 'Open web browser to the path node/1.')]
    #[CLI\Usage(name: 'drush @example.prod browse', description: 'Open a browser to the web site specified in a site alias.')]
    #[CLI\HandleRemoteCommands]
    public function browse($path = '', array $options = ['browser' => true, 'redirect-port' => self::REQ])
    {
        $aliasRecord = $this->siteAliasManager->getSelf();
        // Redispatch if called against a remote-host so a browser is started on the
        // the *local* machine.
        if ($this->processManager()->hasTransport($aliasRecord)) {
            $process = $this->processManager()->drush($aliasRecord, self::BROWSE, [$path], Drush::redispatchOptions());
            $process->mustRun();
            $link = $process->getOutput();
        } else {
            if (!Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL)) {
                // Fail gracefully if unable to bootstrap Drupal. drush_bootstrap() has
                // already logged an error.
                return false;
            }
            $link = Url::fromUserInput('/' . $path, ['absolute' => true])->toString();
        }

        $this->startBrowser($link, 0, $options['redirect-port']);
        return $link;
    }
}
