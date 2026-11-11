<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs `dr cache:rebuild`, the rebuild that ships with Drupal core 11.4+.
 *
 * The Bootstrap attribute is the one Drush-specific piece left: without it
 * Drush's command discovery would bootstrap Drupal fully before loading the
 * command, and the point of shelling out to dr is to rebuild without a
 * bootstrapped Drupal. Everything the command needs (the vendor dir and the
 * selected uri) is known before any bootstrap. For the same reason its
 * messages are plain strings: t() needs Drupal's container.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Rebuild all caches by running dr cache:rebuild. Requires Drupal core 11.4+.',
    aliases: ['cr', 'rebuild', 'cache-rebuild'],
    usages: [
        'cache:rebuild',
        'cache:rebuild --no-cache-clear',
    ],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
class CacheRebuildCommand
{
    use AutowireTrait;

    public const string NAME = 'cache:rebuild';

    public function __construct(
        protected DrushConfig $drushConfig,
        protected BootstrapManager $bootstrapManager,
        protected LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('Extra arguments and options passed through to dr cache:rebuild. Put them after a -- separator.')]
        array $args = [],
        #[Option('Use --no-cache-clear to skip the rebuild; the caller should then rebuild if needed.')]
        bool $cacheClear = true,
    ): int {
        if (!$cacheClear) {
            $output->writeln('Skipping cache:rebuild due to --no-cache-clear.', OutputInterface::VERBOSITY_VERBOSE);
            return Command::SUCCESS;
        }

        $this->logger->info('cache:rebuild is deprecated. Run dr cache:rebuild instead.');

        $binary = $this->drushConfig->get('drush.vendor-dir') . '/bin/dr';
        if (!is_file($binary)) {
            throw new \RuntimeException(sprintf('%s was not found. cache:rebuild runs dr cache:rebuild, which requires Drupal core 11.4 or later.', $binary));
        }

        $command = [$binary, 'cache:rebuild', ...$args];
        if ($output->isDecorated()) {
            $command[] = '--ansi';
        }
        if ($output->isVerbose()) {
            $command[] = '-' . str_repeat('v', min(3, $output->getVerbosity() / OutputInterface::VERBOSITY_VERBOSE));
        }

        $process = new Process($command, null, $this->siteEnvironment(), null, null);
        $process->run(function (string $type, string $buffer) use ($output): void {
            $target = ($type === Process::ERR && $output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
            $target->write($buffer);
        });

        return $process->getExitCode() ?? Command::FAILURE;
    }

    /**
     * Environment variables that make dr select the same site as Drush.
     *
     * dr reads DRUPAL_URI for the commands it boots Drupal for. Its
     * cache:rebuild builds the request from $_SERVER instead, which in CLI
     * holds the environment, so the host is passed as HTTP_HOST as well.
     */
    protected function siteEnvironment(): array
    {
        $uri = $this->bootstrapManager->getUri();
        if (!is_string($uri) || $uri === '') {
            return [];
        }
        if (!str_contains($uri, '://')) {
            $uri = 'http://' . $uri;
        }
        $env = ['DRUPAL_URI' => $uri];
        if ($host = parse_url($uri, PHP_URL_HOST)) {
            $port = parse_url($uri, PHP_URL_PORT);
            $env['HTTP_HOST'] = $host . ($port ? ':' . $port : '');
        }
        return $env;
    }
}
