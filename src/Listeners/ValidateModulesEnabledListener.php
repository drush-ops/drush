<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Attributes\ValidateModulesEnabled;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidateModulesEnabledListener
{
    use AutowireTrait;

    public function __construct(
        private readonly ModuleHandlerInterface $moduleHandler,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     *  This subscriber affects commands which put #[ValidateModulesEnabled] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $reflection = new \ReflectionObject($command);
        $attributes = $reflection->getAttributes(ValidateModulesEnabled::class);
        if (empty($attributes)) {
            return;
        }
        $instance = $attributes[0]->newInstance();
        $missing = array_filter($instance->modules, fn($module) => !$this->moduleHandler->moduleExists($module));
        if ($missing) {
            $message = dt('The following modules are required: !modules', ['!modules' => implode(', ', $missing)]);
            $this->logger->error($message);
            $event->disableCommand();
        }
    }
}
