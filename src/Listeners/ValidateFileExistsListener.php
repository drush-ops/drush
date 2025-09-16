<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes\ValidateFileExists;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidateFileExistsListener
{
    use AutowireTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     *  This subscriber affects commands which put #[ValidateFileExists] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(ValidateFileExists::class);
        if (empty($attributes)) {
            return;
        }
        /** @var ValidateFileExists $instance */
        $instance = $attributes[0]->newInstance();
        $missing = [];
        // @todo handle option as well.
        if (!$command->getDefinition()->hasArgument($instance->argName)) {
            return;
        }
        $paths = StringUtils::csvToArray($event->getInput()->getArgument($instance->argName));
        foreach ($paths as $path) {
            if (!empty($path) && !file_exists($path)) {
                $missing[] = $path;
            }
        }

        if ($missing) {
            $this->logger->error('File(s) not found: {paths}', ['paths' => implode(', ', $missing)]);
            $event->disableCommand();
        }
    }
}
