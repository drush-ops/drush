<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;

#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class LoginCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use AutowireTrait;
    use SiteAliasManagerAwareTrait;
    use ExecTrait;

    const LOGIN = 'user:login';

    public function __construct(
        private BootstrapManager $bootstrapManager
    ) {
        parent::__construct();
    }

    /**
     * Display a one time login link for user ID 1, or another user.
     */
    #[CLI\Command(name: self::LOGIN, aliases: ['uli', 'user-login'])]
    #[CLI\Argument(name: 'path', description: 'Optional path to redirect to after logging in.')]
    #[CLI\Option(name: 'name', description: 'A user name to log in as.')]
    #[CLI\Option(name: 'uid', description: 'A user ID to log in as.')]
    #[CLI\Option(name: 'mail', description: 'A user email to log in as.')]
    #[CLI\Option(name: 'browser', description: 'Open the URL in the default browser. Use --no-browser to avoid opening a browser.')]
    #[CLI\Option(name: 'redirect-port', description: 'A custom port for redirecting to (e.g., when running within a Vagrant environment)')]
    #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
    #[CLI\HandleRemoteCommands]
    #[CLI\Usage(name: 'drush user:login', description: 'Open browser to homepage, logged in as uid=1.')]
    #[CLI\Usage(name: 'drush user:login --name=ryan node/add/blog', description: 'Open browser (if configured or detected) for a one-time login link for username ryan that redirects to node/add/blog.')]
    #[CLI\Usage(name: 'drush user:login --uid=123', description: 'Open browser and login as user with uid "123".')]
    #[CLI\Usage(name: 'drush user:login --mail=foo@bar.com', description: 'Open browser and login as user with mail "foo@bar.com".')]
    public function login(string $path = '', $options = ['name' => null, 'uid' => null, 'mail' => null, 'browser' => true, 'redirect-port' => self::REQ])
    {
        // Redispatch if called against a remote-host so a browser is started on the
        // the *local* machine.
        $aliasRecord = $this->siteAliasManager()->getSelf();
        if ($this->processManager()->hasTransport($aliasRecord)) {
            $process = $this->processManager()->drush($aliasRecord, self::LOGIN, [$path], Drush::redispatchOptions());
            $process->mustRun();
            $link = $process->getOutput();
        } else {
            if (!$this->bootstrapManager->doBootstrap(DrupalBootLevels::FULL)) {
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

            if ($account->isBlocked()) {
                throw new \InvalidArgumentException(dt('Account !name is blocked and thus cannot login. The user:unblock command may be helpful.', ['!name' => $account->getAccountName()]));
            }

            $timestamp = \Drupal::time()->getRequestTime();
            $link = Url::fromRoute(
                'user.reset.login',
                [
                  'uid' => $account->id(),
                  'timestamp' => $timestamp,
                  'hash' => user_pass_rehash($account, $timestamp),
                ],
                [
                  'absolute' => true,
                  'query' => $path ? ['destination' => $path] : [],
                  'language' => \Drupal::languageManager()->getLanguage($account->getPreferredLangcode()),
                ]
            )->toString();
        }
        $port = $options['redirect-port'];
        $this->startBrowser($link, 0, $port, $options['browser']);
        return $link;
    }
}
