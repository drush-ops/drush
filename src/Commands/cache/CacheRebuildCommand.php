<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Doctor\DoctorTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    use DoctorTrait;

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

        $binary = $this->doctorBinary($this->drushConfig, self::NAME);
        return $this->runDoctor($binary, ['cache:rebuild', ...$args], $this->bootstrapManager, $output);
    }
}
