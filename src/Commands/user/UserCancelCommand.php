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
    description: 'Block or delete user account(s) with the specified name(s).',
    aliases: ['ucan', 'user-cancel']
)]
final class UserCancelCommand extends Command
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:cancel';

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected DateFormatterInterface $dateFormatter
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('names', InputArgument::OPTIONAL, 'A comma delimited list of user names.', '')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of user ids to lookup (an alternative to names).')
            ->addOption('mail', null, InputOption::VALUE_REQUIRED, 'A comma delimited list of emails to lookup (an alternative to names).')
            ->addOption('reassign-content', null, InputOption::VALUE_NONE, 'Delete the user and make its content belong to the anonymous user.')
            ->addOption('delete-content', null, InputOption::VALUE_NONE, 'Delete the user, and delete all content created by that user.')
            ->addUsage('user:cancel alice')
            ->addUsage('user:cancel --delete-content alice')
            ->addUsage('user:cancel --reassign-content --uid=12');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $names = $input->getArgument('names');
        $deleteContent = $input->getOption('delete-content');
        $reassignContent = $input->getOption('reassign-content');
        $options = [
            'uid' => $input->getOption('uid'),
            'mail' => $input->getOption('mail'),
        ];

        $accounts = $this->getAccounts($names, $options);
        foreach ($accounts as $id => $account) {
            if ($deleteContent) {
                $io->warning(sprintf('All content created by %s will be deleted.', $account->getAccountName()));
            } elseif ($reassignContent) {
                $io->warning(sprintf('All content created by %s will be assigned to anonymous user.', $account->getAccountName()));
            }
            if ($io->confirm('Cancel user account?: ')) {
                $method = $deleteContent ? 'user_cancel_delete' : ($reassignContent ? 'user_cancel_reassign' : 'user_cancel_block');
                user_cancel([], $account->id(), $method);
                drush_backend_batch_process();
                // Drupal logs a message for us.
            }
        }

        return self::SUCCESS;
    }
}
