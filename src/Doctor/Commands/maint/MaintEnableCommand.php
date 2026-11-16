<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\maint;

use Drupal\Core\State\StateInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * A copy of \Drupal\system\Command\MaintEnableCommand from core merge request 17320,
 * registered with dr only on core versions that do not have that command.
 * Remove it once they all do.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Put the site into maintenance mode.',
)]
final class MaintEnableCommand
{
    public const string NAME = 'maint:enable';

    public function __construct(
        private readonly StateInterface $state,
    ) {
    }

    public function __invoke(): int
    {
        $this->state->set('system.maintenance_mode', true);
        return Command::SUCCESS;
    }
}
