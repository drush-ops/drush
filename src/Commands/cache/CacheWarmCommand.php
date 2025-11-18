<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\PreWarm\CachePreWarmerInterface;
use Drush\Attributes as CLI;
use Drush\Style\DrushStyle;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Pre-warm all caches.',
    aliases: ['cw', 'cache-warm']
)]
#[CLI\Version(version: '13.5')]
final class CacheWarmCommand extends Command
{
    const string NAME = 'cache:warm';

    public function __construct(
        private readonly CachePreWarmerInterface $warmer,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setHelp('Requires Drupal 11.2+. See https://www.drupal.org/node/3386853');
    }


    public static function create(ContainerInterface $container)
    {
        if ($container->has('cache_prewarmer')) {
            return new self($container->get('cache_prewarmer'));
        }
        // Do nothing. Command never gets added to the Application.
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->warmer->preWarmAllCaches();
        (new DrushStyle($input, $output))->success(dt('Warmed caches.'));
        return self::SUCCESS;
    }
}
