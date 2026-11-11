<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\Asset\AssetCollectionOptimizerInterface;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Theme\Registry;
use Drush\Commands\AutowireTrait;
use Drush\Event\CacheClearEvent;
use Drush\Utils\StringUtils;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Clear a specific cache, or all Drupal caches.',
    aliases: ['cc', 'cache-clear'],
    usages: [
        'cache:clear render',
        'cache:clear bin',
        'cache:clear bin entity,bootstrap',
    ],
)]
class CacheClearCommand
{
    use AutowireTrait;
    use CacheTrait;

    public const string NAME = 'cache:clear';
    public const string EVENT_CLEAR = 'cache-clear';

    public function __construct(
        protected CacheFactoryInterface $cacheFactory,
        protected CacheTagsInvalidatorInterface $invalidator,
        protected Registry $themeRegistry,
        protected RouteBuilderInterface $routerBuilder,
        #[Autowire(service: 'asset.js.collection_optimizer')]
        protected AssetCollectionOptimizerInterface $jsOptimizer,
        #[Autowire(service: 'asset.css.collection_optimizer')]
        protected AssetCollectionOptimizerInterface $cssOptimizer,
        protected CachedDiscoveryClearerInterface $pluginCacheClearer,
        protected DrupalKernelInterface $kernel,
        protected AssetQueryStringInterface $assetQueryString,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Prompts for a type, and for a bin, when they were not given.
     */
    #[Interact]
    public function askForType(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($input->getArgument('type'))) {
            $type = $io->choice((string) $this->t('Choose a cache to clear'), array_keys($this->getTypes()), 'render');
            $input->setArgument('type', $type);
        }

        if ($input->getArgument('type') === 'bin' && empty($input->getArgument('args'))) {
            $bin = $io->choice((string) $this->t('Choose a cache bin to clear'), array_keys(Cache::getBins()), 'default');
            $input->setArgument('args', [$bin]);
        }
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The particular cache to clear. Omit this argument to choose from available types.')]
        ?string $type = null,
        #[Argument('Additional arguments as might be expected (e.g. bin name).')]
        array $args = [],
        #[Option('Use --no-cache-clear to suppress normal cache clearing; the caller should then clear if needed.')]
        bool $cacheClear = true,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if (!$cacheClear) {
            $output->writeln((string) $this->t('Skipping cache clear due to --no-cache-clear.'), OutputInterface::VERBOSITY_VERBOSE);
            return Command::SUCCESS;
        }

        $types = $this->getTypes();
        if ($type === null || $type === '') {
            throw new \InvalidArgumentException((string) $this->t('Specify a cache type to clear. Available types: @types', ['@types' => implode(', ', array_keys($types))]));
        }
        if (!array_key_exists($type, $types)) {
            throw new \InvalidArgumentException((string) $this->t("'@type' cache is not a valid cache type. Available types: @types", [
                '@type' => $type,
                '@types' => implode(', ', array_keys($types)),
            ]));
        }

        $types[$type](...$args);

        if ($type === 'bin') {
            $bins = StringUtils::csvToArray($args ?: ['default']);
            $io->success((string) $this->t('Cleared cache bin(s): @bins.', ['@bins' => implode(', ', $bins)]));
        } else {
            $io->success((string) $this->t("'@type' cache was cleared.", ['@type' => $type]));
        }

        return Command::SUCCESS;
    }

    /**
     * Types of caches available for clearing. Listeners can add their own.
     */
    public function getTypes(): array
    {
        $types = [
            'theme-registry' => $this->clearThemeRegistry(...),
            'router' => $this->clearRouter(...),
            'css-js' => $this->clearCssJs(...),
            'render' => $this->clearRender(...),
            'plugin' => $this->clearPlugin(...),
            'bin' => $this->clearBins(...),
            'container' => $this->clearContainer(...),
            'views' => $this->clearViews(...),
        ];

        // Listeners may customize $types as desired.
        $event = new CacheClearEvent($types);
        $this->eventDispatcher->dispatch($event);
        $types = $event->getTypes();
        ksort($types);
        return $types;
    }

    /**
     * Clear one or more cache bins.
     */
    public function clearBins($args = ['default']): void
    {
        $bins = StringUtils::csvToArray($args);
        foreach ($bins as $bin) {
            $this->cacheFactory->get($bin)->deleteAll();
        }
    }

    public function clearThemeRegistry(): void
    {
        $this->themeRegistry->reset();
    }

    public function clearRouter(): void
    {
        $this->routerBuilder->rebuild();
    }

    public function clearCssJs(): void
    {
        $this->assetQueryString->reset();
        $this->cssOptimizer->deleteAll();
        $this->jsOptimizer->deleteAll();
    }

    public function clearContainer(): void
    {
        $this->kernel->invalidateContainer();
    }

    /**
     * Clears the render cache entries.
     */
    public function clearRender(): void
    {
        $this->invalidator->invalidateTags(['rendered']);
    }

    public function clearPlugin(): void
    {
        $this->pluginCacheClearer->clearCachedDefinitions();
    }

    public function clearViews(): void
    {
        \views_invalidate_cache();
    }
}
