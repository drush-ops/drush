<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drush\Commands\AutowireTrait;
use Drush\Exec\ExecTrait;
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
final class UserLoginCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    public const string NAME = 'user:login';

    public function __construct(
        protected readonly LanguageManagerInterface $languageManager,
        protected readonly TimeInterface $time,
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
            ->addUsage('user:login --name=ryan node/add/blog')
            ->addUsage('user:login --uid=123')
            ->addUsage('user:login --mail=foo@bar.com')
            ->setHelp('To avoid the http://default domain in the link, set the [DRUSH_OPTIONS_URI environment variable](https://www.drush.org/latest/using-drush-configuration/#environment-variables).');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
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
        $timestamp = $this->time->getRequestTime();
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
              'language' => $this->languageManager->getLanguage($account->getPreferredLangcode()),
            ]
        )->toString();
        $output->writeln($link);
        return self::SUCCESS;
    }
}
