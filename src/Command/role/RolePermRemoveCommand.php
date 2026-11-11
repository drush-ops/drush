<?php

declare(strict_types=1);

namespace Drush\Command\role;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\PermissionHandlerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
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
    description: 'Remove specified permission(s) from a role.',
    aliases: ['rmp', 'role-remove-perm'],
    usages: ["role:perm:remove anonymous 'post comments,access content'"],
)]
class RolePermRemoveCommand
{
    use AutowireTrait;
    use RoleTrait;

    const string NAME = 'role:perm:remove';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected ?PermissionHandlerInterface $permissionHandler = null,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The role to modify.', suggestedValues: [self::class, 'suggestRoleNames'])]
        string $machineName,
        #[Argument('The list of permission to revoke, delimited by commas.')]
        string $permissions,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if (is_null($this->permissionHandler)) {
            throw new \InvalidArgumentException((string) $this->t('The user module is not installed'));
        }
        $role = $this->loadRole($machineName);
        $perms = StringUtils::csvToArray($permissions);
        $this->validatePermissions($this->permissionHandler, $perms);

        $role->revokePermissions($perms)->save();

        $io->success((string) $this->t('Removed "@perms" permission from "@name" role', ['@perms' => $permissions, '@name' => $machineName]));

        return Command::SUCCESS;
    }
}
