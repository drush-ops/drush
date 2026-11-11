<?php

declare(strict_types=1);

namespace Drush\Command\user;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Command\role\RoleTrait;
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
    description: 'Remove a role from the specified user accounts.',
    aliases: ['urrol', 'user-remove-role'],
    usages: ["user:role:remove 'power_user' user3"],
)]
class UserRoleRemoveCommand
{
    use AutowireTrait;
    use RoleTrait;
    use UserTrait;

    public const string NAME = 'user:role:remove';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        #[Autowire('@logger.channel.user')]
        protected ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The machine name of the role to remove.', suggestedValues: [self::class, 'suggestRoleNames'])]
        string $role,
        #[Argument('A comma delimited list of user names.')]
        string $names = '',
        #[Option('A comma delimited list of user ids to lookup (an alternative to names).')]
        ?string $uid = null,
        #[Option('A comma delimited list of emails to lookup (an alternative to names).')]
        ?string $mail = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $this->loadRole($role);
        foreach ($this->getAccounts($names, $uid, $mail) as $account) {
            $account->removeRole($role);
            $account->save();
            $io->success((string) $this->t('Removed @role role from @name', ['@role' => $role, '@name' => $account->getAccountName()]));
        }

        return Command::SUCCESS;
    }
}
