<?php

declare(strict_types=1);

namespace Drupal\woot\Drush\Listeners;

use Drush\Commands\AutowireTrait;
use Drush\Event\ConsoleDefinitionsEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class WootDefinitionListener
{
    use AutowireTrait;

    public function __construct(protected LoggerInterface $logger)
    {
    }


    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            if ($command->getName() === 'woot:altered') {
                $command->setAliases(['woot-new-alias']);
                // Remove the command keyed with the old alias.
                unset($event->getApplication()[$id]);
                $this->logger->debug(dt("Module 'woot' changed the alias of 'woot:altered' command into 'woot-new-alias' in " . __METHOD__ . '().'));
            }
        }
    }
}
