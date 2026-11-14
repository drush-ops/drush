<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\deploy;

use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs under dr only. Drush keeps its own deploy:mark-complete.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Mark all pending deploy hooks as having run.',
)]
final class DeployMarkCompleteCommand
{
    use DeployTrait;

    public const string NAME = 'deploy:mark-complete';

    public function __construct(
        #[Autowire(service: 'kernel')]
        protected DrupalKernelInterface $kernel,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $registry = $this->registry($this->kernel->getContainer());
        $pending = $registry->getPendingUpdateFunctions();
        $registry->registerInvokedUpdates($pending);

        (new SymfonyStyle($input, $output))->success((string) $this->formatPlural(
            count($pending),
            'Marked 1 pending deploy hook as complete.',
            'Marked @count pending deploy hooks as complete.',
        ));
        return Command::SUCCESS;
    }
}
