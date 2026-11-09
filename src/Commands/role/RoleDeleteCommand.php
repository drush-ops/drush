<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Delete a role.',
    aliases: ['rdel', 'role-delete'],
    usages: ["role:delete 'test_role'"],
)]
class RoleDeleteCommand
{
    use AutowireTrait;
    use RoleTrait;

    const string NAME = 'role:delete';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The machine name for the role.', suggestedValues: [self::class, 'suggestRoleNames'])]
        string $machineName,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $this->loadRole($machineName)->delete();
        $io->success((string) $this->t('Deleted @name role', ['@name' => $machineName]));

        return Command::SUCCESS;
    }
}
