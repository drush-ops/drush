<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes\ValidateEntityLoad;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidateEntityLoadListener
{
    use AutowireTrait;

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     *  This subscriber affects commands which put #[ValidateEntityLoad] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $reflection = new \ReflectionObject($command);
        $attributes = $reflection->getAttributes(ValidateEntityLoad::class);
        if (empty($attributes)) {
            return;
        }
        $instance = $attributes[0]->newInstance();
        $names = StringUtils::csvToArray($event->getInput()->getArgument($instance->argumentName));
        $loaded = $this->entityTypeManager->getStorage($instance->entityType)->loadMultiple($names);
        if ($missing = array_diff($names, array_keys($loaded))) {
            $msg = dt('Unable to load the !type: !str', ['!type' => $instance->entityType, '!str' => implode(', ', $missing)]);
            $this->logger->error($msg);
            $event->disableCommand();
        }
    }
}
