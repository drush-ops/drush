<?php

declare(strict_types=1);

namespace Drush\Commands\user;

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

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Unblock the specified user(s).',
    aliases: ['uublk', 'user-unblock'],
    usages: ['user:unblock user3'],
)]
class UserUnblockCommand
{
    use AutowireTrait;
    use UserTrait;

    public const string NAME = 'user:unblock';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
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
    ): int {
        $io = new SymfonyStyle($input, $output);
        foreach ($this->getAccounts($names, $uid, $mail) as $account) {
            $account->activate();
            $account->save();
            $io->success((string) $this->t('Unblocked user(s): @name', ['@name' => $account->getAccountName()]));
        }

        return Command::SUCCESS;
    }
}
