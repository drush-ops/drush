<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\user\PermissionHandlerInterface;
use Drush\Attributes\ValidatePermissions;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidatePermissionsListener
{
    use AutowireTrait;

    public function __construct(
        private readonly PermissionHandlerInterface $permissionHandler,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     *  This subscriber affects commands which put #[ValidatePermissionsListener] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(ValidatePermissions::class);
        if (empty($attributes)) {
            return;
        }
        /** @var ValidatePermissions $instance */
        $instance = $attributes[0]->newInstance();
        $permissions = StringUtils::csvToArray($event->getInput()->getArgument($instance->argName));
        $all_permissions = array_keys($this->permissionHandler->getPermissions());
        $missing = array_diff($permissions, $all_permissions);
        if ($missing) {
            $this->logger->error('Permission(s) not found: {perms}', ['perms' => implode(', ', $missing)]);
            $event->disableCommand();
        }
    }
}
