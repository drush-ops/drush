<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\deploy;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DrupalKernelInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs under dr only. Drush keeps its own deploy:hook.
 *
 * Hooks run in-process as a non-progressive batch, as in UpdatedbCommand.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Run pending deploy hooks.',
    usages: ['deploy:hook --no-interaction'],
)]
final class DeployHookCommand
{
    use DeployTrait;

    public const string NAME = 'deploy:hook';

    /**
     * The hook that aborted the run, if any.
     */
    private ?string $aborted = null;

    public function __construct(
        #[Autowire(service: 'kernel')]
        protected DrupalKernelInterface $kernel,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $registry = $this->registry($this->kernel->getContainer());
        $rows = $this->pendingHooks($registry);
        if (!$rows) {
            $io->success((string) $this->t('No pending deploy hooks.'));
            return Command::SUCCESS;
        }
        $output->writeln($this->renderTable($rows), OutputInterface::OUTPUT_RAW);
        if (!$io->confirm((string) $this->t('Do you wish to run the specified pending deploy hooks?'))) {
            return Command::FAILURE;
        }

        $builder = (new BatchBuilder())->setTitle((string) $this->t('Deploying'));
        foreach ($registry->getPendingUpdateFunctions() as $function) {
            $builder->addOperation($this->hookOperation(...), [$function, $output]);
        }
        batch_set($builder->toArray());
        $batch = &batch_get();
        $batch['progressive'] = false;
        $this->ensureRoute($this->kernel->getContainer());
        batch_process();

        if ($this->aborted) {
            $io->error((string) $this->t('Deploy hooks aborted by: @function', ['@function' => $this->aborted]));
            return Command::FAILURE;
        }
        $io->success((string) $this->t('Finished performing deploy hooks.'));
        return Command::SUCCESS;
    }

    /**
     * Batch operation: runs one pass of one deploy hook.
     */
    public function hookOperation(string $function, OutputInterface $output, array &$context): void
    {
        // Hooks after a failed one do not run.
        if ($this->aborted) {
            return;
        }
        $args = ['@function' => $function];
        if (empty($context['sandbox'])) {
            $this->logger($output)->notice('Deploy hook started: @function', $args);
        }
        try {
            $message = $function($context['sandbox']);
        } catch (\Throwable $e) {
            $this->aborted = $function;
            $logger = $this->logger($output);
            $logger->error('@message', ['@message' => $e->getMessage()]);
            $logger->error('Deploy hook failed: @function', $args);
            return;
        }
        // A hook may have rebuilt the container, so services are fetched anew.
        $logger = $this->logger($output);
        if ($message) {
            $logger->notice('@message', ['@message' => $this->plainText($message)]);
        }
        $context['finished'] = (float) ($context['sandbox']['#finished'] ?? 1);
        unset($context['sandbox']['#finished']);
        if ($context['finished'] >= 1) {
            $this->registry($this->kernel->getContainer())->registerInvokedUpdates([$function]);
            $logger->notice('Deploy hook completed: @function', $args);
        }
    }

    /**
     * The deploy logger channel of the current container, sent to the console.
     */
    protected function logger(OutputInterface $output): LoggerInterface
    {
        $container = $this->kernel->getContainer();
        $this->attachConsoleLogger($container, $output);
        return $container->get('logger.factory')->get('deploy');
    }
}
