<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Asset\JsCollectionOptimizerLazy;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Theme\Registry;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;

/*
 * Interact with Drupal's Cache API.
 */
final class CacheCommands extends DrushCommands implements CustomEventAwareInterface, StdinAwareInterface
{
    use AutowireTrait;
    use CustomEventAwareTrait;
    use StdinAwareTrait;

    const GET = 'cache:get';
    const TAGS = 'cache:tags';
    const CLEAR = 'cache:clear';
    const SET = 'cache:set';
    const EVENT_CLEAR = 'cache-clear';

    public function __construct(
        private CacheTagsInvalidatorInterface $invalidator,
        private Registry $themeRegistry,
        private RouteBuilderInterface $routerBuilder,
        // @todo Type hints not sufficient for unknown reason.
        #[Autowire(service: 'asset.js.collection_optimizer')]
        private JsCollectionOptimizerLazy $jsOptimizer,
        #[Autowire(service: 'asset.css.collection_optimizer')]
        private $cssOptimizer,
        private CachedDiscoveryClearerInterface $pluginCacheClearer,
        private BootstrapManager $bootstrapManager,
        private AssetQueryStringInterface $assetQueryString
    ) {
        parent::__construct();
    }

    /**
     * Fetch a cached object and display it.
     */
    #[CLI\Command(name: self::GET, aliases: ['cg', 'cache-get'])]
    #[CLI\Argument(name: 'cid', description: 'The id of the object to fetch.')]
    #[CLI\Argument(name: 'bin', description: 'The cache bin to fetch from.')]
    #[CLI\Usage(name: 'drush cache:get hook_info bootstrap', description: 'Display the data for the cache id "hook_info" from the "bootstrap" bin.')]
    #[CLI\Usage(name: 'drush cache:get update_available_releases update', description: 'Display the data for the cache id "update_available_releases" from the "update" bin.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    #[CLI\FieldLabels(labels: [
        'cid' => 'Cache ID',
        'data' => 'Data',
        'created' => 'Created',
        'expire' => 'Expire',
        'tags' => 'Tags',
        'checksum' => 'Checksum',
        'valid' => 'Valid',
    ])]
    #[CLI\DefaultTableFields(fields: ['cid', 'data', 'created', 'expire', 'tags'])]
    public function get($cid, $bin = 'default', $options = ['format' => 'json']): PropertyList
    {
        $result = \Drupal::cache($bin)->get($cid);
        if (empty($result)) {
            throw new \Exception(dt('The !cid object in the !bin bin was not found.', ['!cid' => $cid, '!bin' => $bin]));
        }
        return new PropertyList($result);
    }

    /**
     * Invalidate by cache tags.
     */
    #[CLI\Command(name: self::TAGS, aliases: ['ct'])]
    #[CLI\Argument(name: 'tags', description: 'A comma delimited list of cache tags to clear.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    #[CLI\Usage(name: 'drush cache:tag node:12,user:4', description: 'Purge content associated with two cache tags.')]
    public function tags(string $tags): void
    {
        $tags = StringUtils::csvToArray($tags);
        $this->invalidator->invalidateTags($tags);
        $this->logger()->success(dt("Invalidated tag(s): !list.", ['!list' => implode(' ', $tags)]));
    }

    /**
     * Clear a specific cache, or all Drupal caches.
     */
    #[CLI\Command(name: self::CLEAR, aliases: ['cc', 'cache-clear'])]
    #[CLI\Argument(name: 'type', description: 'The particular cache to clear. Omit this argument to choose from available types.')]
    #[CLI\Argument(name: 'args', description: 'Additional arguments as might be expected (e.g. bin name).')]
    #[CLI\Option(name: 'cache-clear', description: 'Set to 0 to suppress normal cache clearing; the caller should then clear if needed.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
    #[CLI\Usage(name: 'drush cc bin', description: 'Choose a bin to clear.')]
    #[CLI\Usage(name: 'drush cc bin entity,bootstrap', description: 'Clear the entity and bootstrap cache bins.')]
    public function clear(string $type, array $args, $options = ['cache-clear' => true])
    {
        if (!$options['cache-clear']) {
            $this->logger()->info(dt("Skipping cache-clear operation due to --cache-clear=0 option."));
            return null;
        }

        $types = $this->getTypes($this->bootstrapManager->hasBootstrapped((DrupalBootLevels::FULL)));
        drush_op($types[$type], $args);
        // Avoid double confirm.
        if ($type !== 'bin') {
            $this->logger()->success(dt("'!name' cache was cleared.", ['!name' => $type]));
        }
    }

    #[CLI\Hook(type: HookManager::INTERACT, target: self::CLEAR)]
    public function interact($input, $output): void
    {
        if (empty($input->getArgument('type'))) {
            $types = $this->getTypes($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL));
            $choices = array_combine(array_keys($types), array_keys($types));
            $type = $this->io()->select(dt("Choose a cache to clear"), $choices, 'render', scroll: 20);
            $input->setArgument('type', $type);
        }

        if ($input->getArgument('type') == 'bin' && empty($input->getArgument('args'))) {
            $bins = Cache::getBins();
            $choices = array_combine(array_keys($bins), array_keys($bins));
            $chosen = $this->io()->select(dt("Choose a cache to clear"), $choices, 'default', scroll: 20);
            $input->setArgument('args', [$chosen]);
        }
    }

    /**
     * Cache an object expressed in JSON or var_export() format.
     */
    #[CLI\Command(name: self::SET, aliases: ['cs', 'cache-set'])]
    #[CLI\Argument(name: 'cid', description: 'id of the object to set.')]
    #[CLI\Argument(name: 'bin', description: 'The cache bin to store the object in.')]
    #[CLI\Argument(name: 'data', description: 'The object to set in the cache. Use - to read the object from STDIN.')]
    #[CLI\Argument(name: 'expire', description: "'CACHE_PERMANENT', or a Unix timestamp.")]
    #[CLI\Argument(name: 'tags', description: 'A comma delimited list of cache tags.')]
    #[CLI\Option(name: 'input-format', description: 'The format of value. Use <info>json</info> for complex values.')]
    #[CLI\Option(name: 'cache-get', description: "If the object is the result a previous fetch from the cache, only store the value in the 'data' property of the object in the cache.")]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function set($cid, $data, $bin = 'default', $expire = null, $tags = null, $options = ['input-format' => 'string', 'cache-get' => false])
    {
        $tags = is_string($tags) ? StringUtils::csvToArray($tags) : [];
        // In addition to prepare, this also validates. Can't easily be in own validate callback as
        // reading once from STDIN empties it.
        $data = $this->setPrepareData($data, $options);

        if (!isset($expire) || $expire == 'CACHE_PERMANENT') {
            $expire = Cache::PERMANENT;
        }

        return \Drupal::cache($bin)->set($cid, $data, $expire, $tags);
    }

    protected function setPrepareData($data, $options)
    {
        if ($data == '-') {
            $data = $this->stdin()->contents();
        }

        if ($options['input-format'] === 'json') {
            $data = json_decode($data, true);
            if ($data === false) {
                throw new \Exception('Unable to parse JSON.');
            }
        }

        if ($options['cache-get']) {
            // $data might be an object.
            if (is_object($data) && $data->data) {
                $data = $data->data;
            } elseif (is_array($data) && isset($data['data'])) {
                // But $data returned from `drush cache:get --format=json` will be an array.
                $data = $data['data'];
            } else {
                // If $data is neither object nor array and cache-get was specified, then
                // there is a problem.
                throw new \Exception(dt("'cache-get' was specified as an option, but the data is neither an object or an array."));
            }
        }

        return $data;
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::CLEAR)]
    public function validate(CommandData $commandData): void
    {
        $types = $this->getTypes($this->bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL));
        $type = $commandData->input()->getArgument('type');
        // Check if the provided type ($type) is a valid cache type.
        if ($type && !array_key_exists($type, $types)) {
            // If we haven't done a full bootstrap, provide a more
            // specific message with instructions to the user on
            // bootstrapping a Drupal site for more options.
            if (!$this->bootstrapManager->hasBootstrapped(DrupalBootLevels::FULL)) {
                $all_types = $this->getTypes(true);
                if (array_key_exists($type, $all_types)) {
                    throw new \Exception(dt("'!type' cache requires a working Drupal site to operate on. Make sure that the `drush` you are calling is a dependency of a your site\'s composer.json. The --uri option might also help.", ['!type' => $type]));
                } else {
                    throw new \Exception(dt("'!type' cache is not a valid cache type. There may be more cache types available if you select a working Drupal site.", ['!type' => $type]));
                }
            }
            throw new \Exception(dt("'!type' cache is not a valid cache type.", ['!type' => $type]));
        }
    }

    /**
     * Types of caches available for clearing. Contrib commands can hook in their own.
     */
    public function getTypes($include_bootstrapped_types = false): array
    {
        $types = [
            'drush' => [$this, 'clearDrush'],
        ];
        if ($include_bootstrapped_types) {
            $types += [
                'theme-registry' => [$this, 'clearThemeRegistry'],
                'router' => [$this, 'clearRouter'],
                'css-js' => [$this, 'clearCssJs'],
                'render' => [$this, 'clearRender'],
                'plugin' => [$this, 'clearPlugin'],
                'bin' => [$this, 'clearBins'],
                'container' => [$this, 'clearContainer'],
            ];
        }

        // Command files may customize $types as desired.
        $handlers = $this->getCustomEventHandlers(self::EVENT_CLEAR);
        foreach ($handlers as $handler) {
              $handler($types, $include_bootstrapped_types);
        }
        return $types;
    }

    /**
     * Clear caches internal to Drush core.
     */
    public function clearDrush(): void
    {
        try {
            $this->logger()->info(dt('Deprecation notice - Drush no longer caches anything.'));
        } catch (IOException $e) {
            // Sometimes another process writes files into a bin dir and \Drush\Cache\FileCache::clear fails.
            // That is not considered an error. https://github.com/drush-ops/drush/pull/4535.
            $this->logger()->info($e->getMessage());
        }
    }

    /**
     * Clear one or more cache bins.
     */
    public function clearBins($args = ['default']): void
    {
        $bins = StringUtils::csvToArray($args);
        foreach ($bins as $bin) {
            \Drupal::service("cache.$bin")->deleteAll();
            $this->logger()->success("$bin cache bin cleared.");
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
