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
    description: 'Grant specified permission(s) to a role.',
    aliases: ['rap', 'role-add-perm'],
    usages: [
        "role:perm:add anonymous 'post comments'",
        "role:perm:add anonymous 'post comments,access content'",
    ],
)]
class RolePermAddCommand
{
    use AutowireTrait;
    use RoleTrait;

    const string NAME = 'role:perm:add';

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
        #[Argument('The list of permission to grant, delimited by commas.')]
        string $permissions,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if (is_null($this->permissionHandler)) {
            throw new \InvalidArgumentException((string) $this->t('The user module is not installed'));
        }
        $role = $this->loadRole($machineName);
        $perms = StringUtils::csvToArray($permissions);
        $this->validatePermissions($this->permissionHandler, $perms);

        $role->grantPermissions($perms)->save();

        $io->success((string) $this->t('Added "@perms" permission to "@name" role', ['@perms' => $permissions, '@name' => $machineName]));

        return Command::SUCCESS;
    }
}
