<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\updatedb;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DrupalKernelInterface;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs under dr only. Drush keeps its own updatedb:status.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'List pending database updates.',
    aliases: ['updbst'],
    usages: ['updatedb:status --format=json'],
)]
final class UpdatedbStatusCommand
{
    use UpdatedbTrait;

    public const string NAME = 'updatedb:status';

    public function __construct(
        #[Autowire(service: 'kernel')]
        protected DrupalKernelInterface $kernel,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $container = $this->kernel->getContainer();
        $this->attachConsoleLogger($container, $output);
        $this->loadUpdateFiles();
        ['rows' => $rows] = $this->pendingUpdates($container, $this->updateLogger($container));

        if (!$rows) {
            // On the error output, so --format=json still prints [] on stdout.
            (new SymfonyStyle($input, $output))->getErrorStyle()->success((string) $this->t('No database updates required.'));
        }
        $result = match ($format) {
            OutputFormat::Json => json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($rows),
            default => $rows ? $this->renderTable($rows) : '',
        };
        if ($result !== '') {
            $output->writeln($result, OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
