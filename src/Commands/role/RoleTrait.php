<?php

declare(strict_types=1);

namespace Drush\Commands\role;

use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\Console\Completion\CompletionInput;

/**
 * Helpers shared by the role commands.
 *
 * Classes using this trait inject EntityTypeManagerInterface as
 * $this->entityTypeManager.
 */
trait RoleTrait
{
    /**
     * Loads a role or throws when no role has the given machine name.
     *
     * Call this at the top of __invoke() instead of relying on a validation
     * attribute, so the command behaves the same under any console runner.
     */
    protected function loadRole(string $machineName): RoleInterface
    {
        $role = $this->entityTypeManager->getStorage('user_role')->load($machineName);
        if (!$role instanceof RoleInterface) {
            throw new \InvalidArgumentException(sprintf('Unable to load the user_role: %s', $machineName));
        }
        return $role;
    }

    /**
     * Throws when any of the given permissions is not defined by a module.
     *
     * The handler is a parameter rather than a property so that commands
     * which never validate permissions need not inject it.
     */
    protected function validatePermissions(PermissionHandlerInterface $permissionHandler, array $permissions): void
    {
        $known = array_keys($permissionHandler->getPermissions());
        if ($missing = array_diff($permissions, $known)) {
            throw new \InvalidArgumentException(sprintf('Permission(s) not found: %s', implode(', ', $missing)));
        }
    }

    /**
     * Shell completion for the role machine name argument.
     *
     * Referenced from #[Argument(suggestedValues: [self::class, 'suggestRoleNames'])].
     */
    public function suggestRoleNames(CompletionInput $input): array
    {
        return array_keys($this->entityTypeManager->getStorage('user_role')->loadMultiple());
    }
}
