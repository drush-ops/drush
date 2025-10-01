<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes\ValidateConfigName;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ValidateConfigNameListener
{
    use AutowireTrait;

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     *  This subscriber operates on commands which put #[ValidateConfigName] on the *class*.
     *  Method usages are enforced by Annotated Command still.
     */
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        // Support invokable commands (Symfony Console 7.4+).
        $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
        $reflection = new \ReflectionObject($code);
        $attributes = $reflection->getAttributes(ValidateConfigName::class);
        if (empty($attributes)) {
            return;
        }
        /** @var ValidateConfigName $instance */
        $instance = $attributes[0]->newInstance();
        if (!$command->getDefinition()->hasArgument($instance->argumentName)) {
            return;
        }
        $names = StringUtils::csvToArray($event->getInput()->getArgument($instance->argumentName));
        foreach ($names as $name) {
            $config = $this->configFactory->get($name);
            if ($config->isNew()) {
                $this->logger->error('Config {name} does not exist', ['name' => $name]);
                $event->disableCommand();
            }
        }
    }
}
