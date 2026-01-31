<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display a one time login link for user ID 1, or another user.',
    aliases: ['uli', 'user-login'],
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\HandleRemoteCommands]
final class UserLoginCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    public const string NAME = 'user:login';

    public function __construct(
        private readonly BootstrapManager $bootstrapManager,
        protected readonly ProcessManager $processManager,
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Optional path to redirect to after logging in.', '')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'A user name to log in as.')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'A user ID to log in as.')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'A user email to log in as.')
            ->addOption('browser', null, InputOption::VALUE_NEGATABLE, 'Open the URL in the default browser. Use --no-browser to avoid opening a browser.', true)
            ->addOption('redirect-port', null, InputOption::VALUE_REQUIRED, 'A custom port for redirecting to (e.g., when running within a Vagrant environment)')
            ->addUsage('user:login --name=ryan node/add/blog')
            ->addUsage('user:login --uid=123')
            ->addUsage('user:login --mail=foo@bar.com')
            ->setHelp('To avoid the http://default domain in the link, set the [DRUSH_OPTIONS_URI environment variable](https://www.drush.org/latest/using-drush-configuration/#environment-variables).');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        // Redispatch if called against a remote-host so a browser is started on the *local* machine.
        $aliasRecord = $this->siteAliasManager->getSelf();
        if ($this->processManager->hasTransport($aliasRecord)) {
            $process = $this->processManager->drush($aliasRecord, self::NAME, [$path], Drush::redispatchOptions());
            $process->mustRun();
            $link = $process->getOutput();
        } else {
            if (!$this->bootstrapManager->doBootstrap(DrupalBootLevels::FULL)) {
                throw new \Exception('Unable to bootstrap Drupal.');
            }

            $account = null;
            if ($input->getOption('name') && !$account = user_load_by_name($input->getOption('name'))) {
                throw new \Exception(sprintf('Unable to load user by name: %s', $input->getOption('name')));
            }

            if ($input->getOption('uid') && !$account = User::load($input->getOption('uid'))) {
                throw new \Exception(sprintf('Unable to load user by uid: %s', $input->getOption('uid')));
            }

            if ($input->getOption('mail') && !$account = user_load_by_mail($input->getOption('mail'))) {
                throw new \Exception(sprintf('Unable to load user by mail: %s', $input->getOption('mail')));
            }

            if (empty($account)) {
                $account = User::load(1);
            }

            if ($account->isBlocked()) {
                throw new \InvalidArgumentException('Account %s is blocked and thus cannot login. The user:unblock command may be helpful.', $account->getAccountName());
            }

            // Can't inject dependency because this command instantiates without a bootstrap.
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
                  // Can't inject dependency because this command instantiates without a bootstrap.
                  'language' => \Drupal::languageManager()->getLanguage($account->getPreferredLangcode()),
                ]
            )->toString();
        }
        $port = $input->getOption('redirect-port');
//        $browser = $input->getOption('browser');
//        if (is_null($browser) && !str_contains(strval($input), '--browser')) {
//            // WHen user doesn't specify, we have always represented that with true.
//            $browser = true;
//        }
        $this->startBrowser($link, 0, $port, $input->getOption('browser'));
        $output->writeln($link);
        return self::SUCCESS;
    }
}
