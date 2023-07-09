<?php

namespace Drush\Commands\core;

use Drupal\Core\Routing\RouteBuilderInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Boot\DrupalBoot;
use Drush\Commands\DrushCommands;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drupal\Core\Cache\Cache;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Symfony\Component\Filesystem\Exception\IOException;

/*
 * Interact with Drupal's Cache API.
 */
class CacheCommands extends DrushCommands implements CustomEventAwareInterface, AutoloaderAwareInterface, StdinAwareInterface
{
    use CustomEventAwareTrait;
    use AutoloaderAwareTrait;
    use StdinAwareTrait;

    /**
     * Fetch a cached object and display it.
     *
     * @command cache:get
     * @param $cid The id of the object to fetch.
     * @param $bin The cache bin to fetch from.
     * @usage drush cache:get hook_info bootstrap
     *   Display the data for the cache id "hook_info" from the "bootstrap" bin.
     * @usage drush cache:get update_available_releases update
     *   Display the data for the cache id "update_available_releases" from the "update" bin.
     * @aliases cg,cache-get
     * @bootstrap full
     * @field-labels
     *   cid: Cache ID
     *   data: Data
     *   created: Created
     *   expire: Expire
     *   tags: Tags
     *   checksum: Checksum
     *   valid: Valid
     * @default-fields cid,data,created,expire,tags
     */
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
     *
     * @command cache:tags
     * @param string $tags A comma delimited list of cache tags to clear.
     * @aliases ct
     * @bootstrap full
     * @usage drush cache:tag node:12,user:4
     *   Purge content associated with two cache tags.
     */
    public function tags(string $tags): void
    {
        $tags = StringUtils::csvToArray($tags);
        Cache::invalidateTags($tags);
        $this->logger()->success(dt("Invalidated tag(s): !list.", ['!list' => implode(' ', $tags)]));
    }

    /**
     * Clear a specific cache, or all Drupal caches.
     *
     * @command cache:clear
     * @param string $type The particular cache to clear. Omit this argument to choose from available types.
     * @param array $args Additional arguments as might be expected (e.g. bin name).
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @hidden-options cache-clear
     * @aliases cc,cache-clear
     * @bootstrap max
     * @notify Caches have been cleared.
     * @usage drush cc bin
     *   Choose a bin to clear.
     * @usage drush cc bin entity,bootstrap
     *   Clear the entity and bootstrap cache bins.
     */
    public function clear(string $type, array $args, $options = ['cache-clear' => true])
    {
        $boot_manager = Drush::bootstrapManager();

        if (!$options['cache-clear']) {
            $this->logger()->info(dt("Skipping cache-clear operation due to --cache-clear=0 option."));
            return null;
        }

        $types = $this->getTypes($boot_manager->hasBootstrapped((DRUSH_BOOTSTRAP_DRUPAL_FULL)));

        // Do it.
        drush_op($types[$type], $args);
        // Avoid double confirm.
        if ($type !== 'bin') {
            $this->logger()->success(dt("'!name' cache was cleared.", ['!name' => $type]));
        }
    }

    /**
     * @hook interact cache-clear
     */
    public function interact($input, $output): void
    {
        $boot_manager = Drush::bootstrapManager();
        if (empty($input->getArgument('type'))) {
            $types = $this->getTypes($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));
            $choices = array_combine(array_keys($types), array_keys($types));
            $type = $this->io()->choice(dt("Choose a cache to clear"), $choices, 'all');
            $input->setArgument('type', $type);
        }

        if ($input->getArgument('type') == 'bin' && empty($input->getArgument('args'))) {
            $bins = Cache::getBins();
            $choices = array_combine(array_keys($bins), array_keys($bins));
            $chosen = $this->io()->choice(dt("Choose a cache to clear"), $choices, 'default');
            $input->setArgument('args', [$chosen]);
        }
    }

    /**
     * Cache an object expressed in JSON or var_export() format.
     *
     * @command cache:set
     * @param $cid The id of the object to set.
     * @param $data The object to set in the cache. Use - to read the object from STDIN.
     * @param $bin The cache bin to store the object in.
     * @param $expire 'CACHE_PERMANENT', or a Unix timestamp.
     * @param $tags A comma delimited list of cache tags.
     * @option input-format The format of value. Use <info>json</info> for complex values.
     * @option cache-get If the object is the result a previous fetch from the cache, only store the value in the 'data' property of the object in the cache.
     * @aliases cs,cache-set
     * @bootstrap full
     */
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

        // Now, we parse the object.
        switch ($options['input-format']) {
            case 'json':
                $data = json_decode($data, true);
                if ($data === false) {
                    throw new \Exception('Unable to parse JSON.');
                }
                break;
        }

        if ($options['cache-get']) {
            // $data might be an object.
            if (is_object($data) && $data->data) {
                $data = $data->data;
            } elseif (is_array($data) && isset($data['data'])) {
                // But $data returned from `drush cache-get --format=json` will be an array.
                $data = $data['data'];
            } else {
                // If $data is neither object nor array and cache-get was specified, then
                // there is a problem.
                throw new \Exception(dt("'cache-get' was specified as an option, but the data is neither an object or an array."));
            }
        }

        return $data;
    }

    /**
     * Rebuild all caches.
     *
     * This is a copy of core/rebuild.php.
     *
     * @command cache:rebuild
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @hidden-options cache-clear
     * @aliases cr,rebuild,cache-rebuild
     * @bootstrap site
     */
    public function rebuild($options = ['cache-clear' => true])
    {
        if (!$options['cache-clear']) {
            $this->logger()->info(dt("Skipping cache-clear operation due to --no-cache-clear option."));
            return true;
        }

        // We no longer clear APC and similar caches as they are useless on CLI.
        // See https://github.com/drush-ops/drush/pull/2450
        $root  = Drush::bootstrapManager()->getRoot();
        $autoloader = $this->loadDrupalAutoloader($root);
        require_once DRUSH_DRUPAL_CORE . '/includes/utility.inc';

        $request = Drush::bootstrap()->getRequest();
        DrupalKernel::bootEnvironment();

        $site_path = DrupalKernel::findSitePath($request);
        Settings::initialize($root, $site_path, $autoloader);

        // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
        drupal_rebuild($autoloader, $request);
        $this->logger()->success(dt('Cache rebuild complete.'));
    }

    /**
     * @hook validate cache-clear
     */
    public function validate(CommandData $commandData): void
    {
        $boot_manager = Drush::bootstrapManager();
        $types = $this->getTypes($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));
        $type = $commandData->input()->getArgument('type');
        // Check if the provided type ($type) is a valid cache type.
        if ($type && !array_key_exists($type, $types)) {
            if ($type === 'all') {
                throw new \Exception(dt('`cache-clear all` is deprecated for Drupal 8 and later. Please use the `cache:rebuild` command instead.'));
            }
            // If we haven't done a full bootstrap, provide a more
            // specific message with instructions to the user on
            // bootstrapping a Drupal site for more options.
            if (!$boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
                $all_types = $this->getTypes(true);
                if (array_key_exists($type, $all_types)) {
                    throw new \Exception(dt("'!type' cache requires a working Drupal site to operate on. Use the --root and --uri options, or a site @alias, or cd to a directory containing a Drupal settings.php file.", ['!type' => $type]));
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
        $handlers = $this->getCustomEventHandlers('cache-clear');
        foreach ($handlers as $handler) {
              $handler($types, $include_bootstrapped_types);
        }
        return $types;
    }

    /**
     * Clear caches internal to Drush core.
     */
    public static function clearDrush(): void
    {
        try {
            Drush::logger()->info(dt('Deprecation notice - Drush no longer caches anything.'));
        } catch (IOException $e) {
            // Sometimes another process writes files into a bin dir and \Drush\Cache\FileCache::clear fails.
            // That is not considered an error. https://github.com/drush-ops/drush/pull/4535.
            Drush::logger()->info($e->getMessage());
        }
    }

    /**
     * Clear one or more cache bins.
     */
    public static function clearBins($args = ['default']): void
    {
        $bins = StringUtils::csvToArray($args);
        foreach ($bins as $bin) {
            \Drupal::service("cache.$bin")->deleteAll();
            Drush::logger()->success("$bin cache bin cleared.");
        }
    }

    public static function clearThemeRegistry(): void
    {
        \Drupal::service('theme.registry')->reset();
    }

    public static function clearRouter(): void
    {
        /** @var RouteBuilderInterface $router_builder */
        $router_builder = \Drupal::service('router.builder');
        $router_builder->rebuild();
    }

    public static function clearCssJs(): void
    {
        _drupal_flush_css_js();
        \Drupal::service('asset.css.collection_optimizer')->deleteAll();
        \Drupal::service('asset.js.collection_optimizer')->deleteAll();
    }

    public static function clearContainer(): void
    {
        /** @var DrupalBoot $boot_object */
        $boot_object = Drush::bootstrap();
        $boot_object->getKernel()->invalidateContainer();
    }

    /**
     * Clears the render cache entries.
     */
    public static function clearRender(): void
    {
        Cache::invalidateTags(['rendered']);
    }

    public static function clearPlugin(): void
    {
        \Drupal::getContainer()->get('plugin.cache_clearer')->clearCachedDefinitions();
    }

    /**
     * Loads the Drupal autoloader and returns the instance.
     */
    public function loadDrupalAutoloader($drupal_root)
    {
        static $autoloader = false;

        $autoloadFilePath = $drupal_root . '/autoload.php';
        if (!$autoloader && file_exists($autoloadFilePath)) {
            $autoloader = require $autoloadFilePath;
        }

        if ($autoloader === true) {
            // The autoloader was already required. Assume that Drush and Drupal share an autoloader per
            // "Point autoload.php to the proper vendor directory" - https://www.drupal.org/node/2404989
            $autoloader = $this->autoloader();
        }

        return $autoloader;
    }
}
