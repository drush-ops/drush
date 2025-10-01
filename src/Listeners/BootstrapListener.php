<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Attributes\Bootstrap;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
class BootstrapListener
{
    use AutowireTrait;

    public function __construct(
        protected BootstrapManager $bootstrapManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Bootstrap command up to the level specified by the #[Bootstrap] attribute.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(Bootstrap::class);
        if (empty($attributes)) {
            return;
        }
        /** @var \Drush\Attributes\Bootstrap $instance */
        $instance = $attributes[0]->newInstance();
        if ($instance->max_level) {
            $success = $this->bootstrapManager->bootstrapMax($instance->max_level);
        } else {
            $success = $this->bootstrapManager->bootstrapToPhaseIndex($instance->level);
        }
        if (!$success) {
            $message = 'Bootstrap failed';
            if (!Drush::verbose()) {
                $message .= ' Run your command with -vvv for more information.';
            }
            $this->logger->error($message);
            $event->disableCommand();
        }
    }
}
