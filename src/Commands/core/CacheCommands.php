<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Boot\AutoloaderAwareInterface;
use Drush\Boot\AutoloaderAwareTrait;
use Drush\Commands\DrushCommands;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drupal\Core\Cache\Cache;
use Drush\Drush;
use Symfony\Component\HttpFoundation\Request;

/*
 * Interact with Drupal's Cache API.
 */
class CacheCommands extends DrushCommands implements CustomEventAwareInterface, AutoloaderAwareInterface
{

    use CustomEventAwareTrait;
    use AutoloaderAwareTrait;

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
     * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
     */
    public function get($cid, $bin = 'default', $options = ['format' => 'json'])
    {
        $result = \Drupal::cache($bin)->get($cid);
        if (empty($result)) {
            throw new \Exception(dt('The !cid object in the !bin bin was not found.', ['!cid' => $cid, '!bin' => $bin]));
        }
        return new PropertyList($result);
    }

    /**
     * Clear a specific cache, or all Drupal caches.
     *
     * @command cache:clear
     * @param string $type The particular cache to clear. Omit this argument to choose from available types.
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @hidden-options cache-clear
     * @aliases cc,cache-clear
     * @bootstrap max
     * @notify Caches have been cleared.
     */
    public function clear($type, $options = ['cache-clear' => true])
    {
        $boot_manager = Drush::bootstrapManager();

        if (!$options['cache-clear']) {
            $this->logger()->info(dt("Skipping cache-clear operation due to --cache-clear=0 option."));
            return null;
        }

        $types = $this->getTypes($boot_manager->hasBootstrapped((DRUSH_BOOTSTRAP_DRUPAL_FULL)));

        // Do it.
        drush_op($types[$type]);
        $this->logger()->success(dt("'!name' cache was cleared.", ['!name' => $type]));
    }

    /**
     * @hook interact cache-clear
     */
    public function interact($input, $output)
    {
        $boot_manager = Drush::bootstrapManager();
        if (empty($input->getArgument('type'))) {
            $types = $this->getTypes($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));
            $choices = array_combine(array_keys($types), array_keys($types));
            $type = $this->io()->choice(dt("Choose a cache to clear"), $choices, 'all');
            $input->setArgument('type', $type);
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
     * @option input-format The format of value. Use 'json' for complex values.
     * @option cache-get If the object is the result a previous fetch from the cache, only store the value in the 'data' property of the object in the cache.
     * @aliases cs,cache-set
     * @bootstrap full
     */
    public function set($cid, $data, $bin = 'default', $expire = null, $tags = null, $options = ['input-format' => 'string', 'cache-get' => false])
    {
        $tags = is_string($tags) ? _convert_csv_to_array($tags) : [];

        // In addition to prepare, this also validates. Can't easily be in own validate callback as
        // reading once from STDIN empties it.
        $data = $this->setPrepareData($data, $options);
        if ($data === false && drush_get_error()) {
            // An error was logged above.
            return;
        }

        if (!isset($expire) || $expire == 'CACHE_PERMANENT') {
            $expire = Cache::PERMANENT;
        }

        return \Drupal::cache($bin)->set($cid, $data, $expire, $tags);
    }

    protected function setPrepareData($data, $options)
    {
        if ($data == '-') {
            $data = file_get_contents("php://stdin");
        }

        // Now, we parse the object.
        switch ($options['input-format']) {
            case 'json':
                $data = json_decode($data, true);
                break;
        }

        if ($options['cache-get']) {
            // $data might be an object.
            if (is_object($data) && $data->data) {
                $data = $data->data;
            } // But $data returned from `drush cache-get --format=json` will be an array.
            elseif (is_array($data) && isset($data['data'])) {
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
     * Rebuild a Drupal 8 site.
     *
     * This is a copy of core/rebuild.php. Additionally
     * it also clears Drush cache and Drupal's render cache.

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
        chdir(DRUPAL_ROOT);

        // We no longer clear APC and similar caches as they are useless on CLI.
        // See https://github.com/drush-ops/drush/pull/2450

        $autoloader = $this->loadDrupalAutoloader(DRUPAL_ROOT);
        require_once DRUSH_DRUPAL_CORE . '/includes/utility.inc';

        $request = Drush::bootstrap()->getRequest();
        // Manually resemble early bootstrap of DrupalKernel::boot().
        require_once DRUSH_DRUPAL_CORE . '/includes/bootstrap.inc';
        DrupalKernel::bootEnvironment();

        // Avoid 'Only variables should be passed by reference'
        $root  = DRUPAL_ROOT;
        $site_path = DrupalKernel::findSitePath($request);
        Settings::initialize($root, $site_path, $autoloader);

        // Use our error handler since _drupal_log_error() depends on an unavailable theme system (ugh).
        set_error_handler('drush_error_handler');

        // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
        drupal_rebuild($autoloader, $request);
        $this->logger()->success(dt('Cache rebuild complete.'));

        // As this command replaces `drush cache-clear all` for Drupal 8 users, clear
        // the Drush cache as well, for consistency with that behavior.
        CacheCommands::clearDrush();
    }

    /**
     * @hook validate cache-clear
     */
    public function validate(CommandData $commandData)
    {
        $boot_manager = Drush::bootstrapManager();
        $types = $this->getTypes($boot_manager->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));
        $type = $commandData->input()->getArgument('type');
        // Check if the provided type ($type) is a valid cache type.
        if ($type && !array_key_exists($type, $types)) {
            if ($type === 'all') {
                throw new \Exception(dt('`cache-clear all` is deprecated for Drupal 8 and later. Please use the `cache-rebuild` command instead.'));
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
    public function getTypes($include_bootstrapped_types = false)
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
    public static function clearDrush()
    {
        drush_cache_clear_all(null, 'default'); // commandfiles, etc.
        drush_cache_clear_all(null, 'factory'); // command info from annotated-command library
    }

    public static function clearThemeRegistry()
    {
        \Drupal::service('theme.registry')->reset();
    }

    public static function clearRouter()
    {
        /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
        $router_builder = \Drupal::service('router.builder');
        $router_builder->rebuild();
    }

    public static function clearCssJs()
    {
        _drupal_flush_css_js();
        \Drupal::service('asset.css.collection_optimizer')->deleteAll();
        \Drupal::service('asset.js.collection_optimizer')->deleteAll();
    }

    /**
     * Clears the render cache entries.
     */
    public static function clearRender()
    {
        Cache::invalidateTags(['rendered']);
    }

    /**
     * Loads the Drupal autoloader and returns the instance.
     */
    public function loadDrupalAutoloader($drupal_root)
    {
        static $autoloader = false;

        $autoloadFilePath = $drupal_root .'/autoload.php';
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
