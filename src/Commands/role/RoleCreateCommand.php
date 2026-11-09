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
    description: 'Create a new role.',
    aliases: ['rcrt', 'role-create'],
    usages: ["role:create 'test_role' 'Test role'"],
)]
class RoleCreateCommand
{
    use AutowireTrait;
    use RoleTrait;

    const string NAME = 'role:create';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The symbolic machine name for the role.')]
        string $machineName,
        #[Argument('A descriptive name for the role.')]
        ?string $humanReadableName = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $storage = $this->entityTypeManager->getStorage('user_role');
        if ($storage->load($machineName)) {
            throw new \InvalidArgumentException(sprintf('The user_role already exists: %s', $machineName));
        }
        $storage->create([
            'id' => $machineName,
            'label' => $humanReadableName ?: ucfirst($machineName),
        ])->save();

        $io->success(sprintf('Created "%s" role', $machineName));

        return Command::SUCCESS;
    }
}
