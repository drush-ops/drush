<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Asset\JsCollectionOptimizerLazy;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Theme\Registry;
use Drush\Boot\BootstrapManager;
use Drush\Commands\AutowireTrait;
use Drush\Event\CacheClearEvent;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'Clear a specific cache, or all Drupal caches.',
    aliases: ['cc', 'cache-clear'],
)]
final class CacheClearCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'cache:clear';
    public const EVENT_CLEAR = 'cache-clear';

    public function __construct(
        private readonly CacheFactoryInterface $cacheFactory,
        private readonly CacheTagsInvalidatorInterface $invalidator,
        private readonly Registry $themeRegistry,
        private readonly RouteBuilderInterface $routerBuilder,
        #[Autowire(service: 'asset.js.collection_optimizer')]
        private readonly JsCollectionOptimizerLazy $jsOptimizer,
        #[Autowire(service: 'asset.css.collection_optimizer')]
        private $cssOptimizer,
        private readonly CachedDiscoveryClearerInterface $pluginCacheClearer,
        private readonly BootstrapManager $bootstrapManager,
        private readonly AssetQueryStringInterface $assetQueryString,
        protected EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::OPTIONAL, 'The particular cache to clear. Omit this argument to choose from available types.')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Additional arguments as might be expected (e.g. bin name).')
            ->addOption('cache-clear', null, InputOption::VALUE_NEGATABLE, 'Set to 0 to suppress normal cache clearing; the caller should then clear if needed.', true)
            ->addUsage('cc bin')
            ->addUsage('cc bin entity,bootstrap');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new DrushStyle($input, $output);

        if (empty($input->getArgument('type'))) {
            $types = $this->getTypes();
            $choices = array_combine(array_keys($types), array_keys($types));
            $type = $io->select("Choose a cache to clear", $choices, 'render', scroll: 20);
            $input->setArgument('type', $type);
        }

        if ($input->getArgument('type') == 'bin' && empty($input->getArgument('args'))) {
            $bins = Cache::getBins();
            $choices = array_combine(array_keys($bins), array_keys($bins));
            $chosen = $io->select("Choose a cache to clear", $choices, 'default', scroll: 20);
            $input->setArgument('args', [$chosen]);
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $args = $input->getArgument('args');
        $cache_clear = $input->getOption('cache-clear');

        if (!$cache_clear) {
            $this->logger->info("Skipping cache-clear operation due to --cache-clear=0 option.");
            return self::SUCCESS;
        }

        $this->validateType($type);

        $types = $this->getTypes();
        drush_op($types[$type], $args);

        // Avoid double confirm.
        if ($type !== 'bin') {
            $this->logger->notice(sprintf("'%s' cache was cleared.", $type));
        }
        return Command::SUCCESS;
    }

    private function validateType(?string $type): void
    {
        if (!$type) {
            return;
        }

        $types = $this->getTypes();

        // Check if the provided type ($type) is a valid cache type.
        if (!array_key_exists($type, $types)) {
            throw new \Exception(sprintf("'%s' cache is not a valid cache type.", $type));
        }
    }

    /**
     * Types of caches available for clearing. Listeners can add their own.
     */
    public function getTypes(): array
    {
        $types = [
            'theme-registry' => [$this, 'clearThemeRegistry'],
            'router' => [$this, 'clearRouter'],
            'css-js' => [$this, 'clearCssJs'],
            'render' => [$this, 'clearRender'],
            'plugin' => [$this, 'clearPlugin'],
            'bin' => [$this, 'clearBins'],
            'container' => [$this, 'clearContainer'],
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
            $this->logger->notice("$bin cache bin cleared.");
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
        $boot_object = $this->bootstrapManager->bootstrap();
        $boot_object->getKernel()->invalidateContainer();
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
}
