<?php

declare(strict_types=1);

namespace Drush\Commands\user;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Unblock the specified user(s).',
    aliases: ['uublk', 'user-unblock']
)]
final class UserUnblockCommand extends Command
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:unblock';

    public function __construct(
        protected DateFormatterInterface $dateFormatter,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('names', InputArgument::OPTIONAL, 'A comma delimited list of user names.', '')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of user ids to lookup (an alternative to names).')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of emails to lookup (an alternative to names).')
            ->addUsage('user:unblock user3');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $names = $input->getArgument('names');
        $options = [
            'uid' => $input->getOption('uid'),
            'mail' => $input->getOption('mail'),
        ];

        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            $account->activate();
            $account->save();
            $io->success(sprintf('Unblocked user(s): %s', $account->getAccountName()));
        }

        return self::SUCCESS;
    }
}
