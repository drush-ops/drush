<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\deploy;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Doctor\Commands\UpdatedbCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs under dr only. Drush keeps its own deploy.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Run several commands after performing a code deployment. The commands run without prompting.',
)]
final class DeployCommand
{
    use StringTranslationTrait;

    public const string NAME = 'deploy';

    /**
     * The steps to run, in order. All but the cache rebuild are commands.
     */
    private const array STEPS = [
        UpdatedbCommand::NAME,
        'config:import',
        self::REBUILD,
        DeployHookCommand::NAME,
        'cache:warm',
    ];

    /**
     * Done here: core's cache:rebuild boots a second kernel and points
     * \Drupal at its container, which leaves the commands that follow with a
     * kernel that Drupal no longer uses.
     */
    private const string REBUILD = 'cache rebuild';

    public function __construct(
        #[Autowire(service: 'kernel')]
        protected DrupalKernelInterface $kernel,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output, Application $application): int
    {
        $io = new SymfonyStyle($input, $output);
        $commands = array_diff(self::STEPS, [self::REBUILD]);
        // Checked up front so a deployment is not left half done.
        if ($missing = array_filter($commands, fn(string $name): bool => !$application->has($name))) {
            $io->error((string) $this->t('deploy needs these commands, which are not available: @names', ['@names' => implode(', ', $missing)]));
            return Command::FAILURE;
        }

        foreach (self::STEPS as $name) {
            $io->section($name);
            $container = $this->kernel->getContainer();
            $code = match ($name) {
                self::REBUILD => $this->rebuild(),
                default => $this->runCommand($name, $application, $output),
            };
            if ($code !== Command::SUCCESS) {
                $io->error((string) $this->t('@name failed. Deployment stopped.', ['@name' => $name]));
                return $code;
            }
            // Later commands must come from the container the step left
            // behind, not the one this command was built from.
            if ($this->kernel->getContainer() !== $container) {
                $application->setCommandLoader($this->kernel->getContainer()->get('console.command_loader'));
                $application->setDispatcher($this->kernel->getContainer()->get('event_dispatcher'));
            }
        }

        $io->success((string) $this->t('Deployment complete.'));
        return Command::SUCCESS;
    }

    protected function runCommand(string $name, Application $application, OutputInterface $output): int
    {
        $input = new ArrayInput(['command' => $name]);
        // A deployment is not the place to answer prompts.
        $input->setInteractive(false);
        return $application->doRun($input, $output);
    }

    protected function rebuild(): int
    {
        drupal_flush_all_caches($this->kernel);
        return Command::SUCCESS;
    }
}
