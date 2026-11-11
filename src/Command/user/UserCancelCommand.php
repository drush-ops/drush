<?php

declare(strict_types=1);

namespace Drush\Command\user;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Block or delete user account(s) with the specified name(s).',
    aliases: ['ucan', 'user-cancel'],
    usages: [
        'user:cancel alice',
        'user:cancel --delete-content alice',
        'user:cancel --reassign-content --uid=12',
    ],
)]
class UserCancelCommand
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:cancel';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        #[Autowire('@logger.channel.user')]
        protected LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('A comma delimited list of user names.')]
        string $names = '',
        #[Option('A comma delimited list of user ids to lookup (an alternative to names).')]
        ?string $uid = null,
        #[Option('A comma delimited list of emails to lookup (an alternative to names).')]
        ?string $mail = null,
        #[Option('Delete the user and make its content belong to the anonymous user.')]
        bool $reassignContent = false,
        #[Option('Delete the user, and delete all content created by that user.')]
        bool $deleteContent = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $method = $deleteContent ? 'user_cancel_delete' : ($reassignContent ? 'user_cancel_reassign' : 'user_cancel_block');
        foreach ($this->getAccounts($names, $uid, $mail) as $account) {
            if ($deleteContent) {
                $io->warning((string) $this->t('All content created by @name will be deleted.', ['@name' => $account->getAccountName()]));
            } elseif ($reassignContent) {
                $io->warning((string) $this->t('All content created by @name will be assigned to anonymous user.', ['@name' => $account->getAccountName()]));
            }
            if (!$io->confirm((string) $this->t('Cancel user account?'))) {
                continue;
            }
            // user_cancel() queues the work as a batch, which is processed
            // here in a single pass. Drupal logs a message for us.
            user_cancel([], (int) $account->id(), $method);
            $batch = &batch_get();
            $batch['progressive'] = false;
            batch_process();
        }

        return Command::SUCCESS;
    }
}
