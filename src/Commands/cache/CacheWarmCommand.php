<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\PreWarm\CachePreWarmerInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Pre-warm all caches.',
    aliases: ['cw', 'cache-warm'],
    help: 'See https://www.drupal.org/node/3386853',
)]
class CacheWarmCommand
{
    use AutowireTrait;
    use CacheTrait;

    public const string NAME = 'cache:warm';

    public function __construct(
        protected CachePreWarmerInterface $warmer,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $this->warmer->preWarmAllCaches();
        $io = new SymfonyStyle($input, $output);
        $io->success((string) $this->t('Warmed caches.'));

        return Command::SUCCESS;
    }
}
