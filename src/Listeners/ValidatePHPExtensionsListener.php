<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes\ValidatePhpExtensions;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidatePHPExtensionsListener
{
    use AutowireTrait;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     *  This subscriber affects commands which put #[ValidatePhpExtensions] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(ValidatePhpExtensions::class);
        if (empty($attributes)) {
            return;
        }
        $instance = $attributes[0]->newInstance();
        $missing = array_filter($instance->extensions, fn($extension) => !extension_loaded($extension));
        if ($missing) {
            $this->logger->error('The following PHP extensions are required: {extensions}', ['extensions' => implode(', ', $missing)]);
            $event->disableCommand();
        }
    }
}
