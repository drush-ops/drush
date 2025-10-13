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
    description: 'Block the specified user(s).',
    aliases: ['ublk', 'user-block']
)]
final class UserBlockCommand extends Command
{
    use AutowireTrait;
    use UserTrait;

    public const NAME = 'user:block';

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected DateFormatterInterface $dateFormatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('names', InputArgument::OPTIONAL, 'A comma delimited list of user names.', '')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of user ids to lookup (an alternative to names).')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of emails to lookup (an alternative to names).')
            ->addUsage('user:block user3');
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
            $account->block();
            $account->save();
            $io->success(sprintf('Blocked user(s): %s', $account->getAccountName()));
        }

        return self::SUCCESS;
    }
}
