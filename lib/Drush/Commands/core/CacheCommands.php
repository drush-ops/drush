<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/*
 * Interact with Drupal's cache API.
 */
class CacheCommands extends DrushCommands implements CustomEventAwareInterface {

  use CustomEventAwareTrait;

  /**
   * Fetch a cached object and display it.
   *
   * @command cache-get
   * @param $cid The id of the object to fetch.
   * @param $bin The cache bin to fetch from.
   * @usage drush cache-get hook_info bootstrap
   *   Display the data for the cache id "hook_info" from the "bootstrap" bin.
   * @usage drush cache-get update_available_releases update
   *   Display the data for the cache id "update_available_releases" from the "update" bin.
   * @aliases cg
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
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
  public function get($cid, $bin = NULL, $options = ['format' => 'json']) {
    drush_include_engine('drupal', 'cache');
    $result = drush_op('_drush_cache_command_get', $cid, $bin);

    if (empty($result)) {
      throw new \Exception(dt('The !cid object in the !bin bin was not found.', array('!cid' => $cid, '!bin' => $bin ? $bin : _drush_cache_bin_default())));
    }
    return new PropertyList($result);
  }

  /**
   * Clear a specific cache, or all Drupal caches.
   *
   * @command cache-clear
   * @param $type The particular cache to clear. Omit this argument to choose from available caches.
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @hidden-option cache-clear
   * @aliases cc
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   * @complete \Drush\Commands\core\CacheCommands::complete
   */
  public function clear($type = NULL, $options = ['cache-clear' => TRUE]) {
    if (!$options['cache-clear']) {
      $this->logger()->info(dt("Skipping cache-clear operation due to --cache-clear=0 option."));
      return NULL;
    }

    $types = $this->getTypes(drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));

    if (empty($type)) {
      // Don't offer 'all' unless Drush has bootstrapped the Drupal site
      if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
        unset($types['all']);
      }
      $type = drush_choice($types, 'Enter a number to choose which cache to clear.', '!key');
      if (empty($type)) {
        return drush_user_abort();
      }
    }

    // Do it.
    drush_op($types[$type]);
    // @todo 'all' only applies to D7.
    if ($type == 'all' && !drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
      $this->logger()->warning(dt("No Drupal site found, only 'drush' cache was cleared."));
    }
    else {
      $this->logger()->success(dt("'!name' cache was cleared.", array('!name' => $type)));
    }
  }

  /**
   * Cache an object expressed in JSON or var_export() format.
   *
   * @command cache-set
   * @param $cid The id of the object to set.
   * @param $data The object to set in the cache. Use \'-\' to read the object from STDIN.
   * @param $bin The cache bin to store the object in.
   * @param $expire CACHE_PERMANENT, CACHE_TEMPORARY, or a Unix timestamp.
   * @param $tags A comma delimited list of cache tags.
   * @option input-format The format of value. Use 'json' for complex values.
   * @option cache-get If the object is the result a previous fetch from the cache, only store the value in the "data" property of the object in the cache.
   * @aliases cs
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function set($cid, $data, $bin = NULL, $expire = NULL, $tags = NULL, $options = ['input-format' => 'string', 'cache-get' => FALSE]) {
    $tags = is_string($tags) ? _convert_csv_to_array($tags) : [];

    // In addition to prepare, this also validates. Can't easily be in own validate callback as
    // reading once from STDIN empties it.
    $data = $this->setPrepareData($data, $options);
    if ($data === FALSE && drush_get_error()) {
      // An error was logged above.
      return;
    }

    drush_include_engine('drupal', 'cache');
    return drush_op('_drush_cache_command_set', $cid, $data, $bin, $expire, $tags);
  }

  protected function setPrepareData($data, $options) {
    if ($data == '-') {
      $data = file_get_contents("php://stdin");
    }

    // Now, we parse the object.
    switch ($options['input-format']) {
      case 'json':
        $data = drush_json_decode($data);
        break;
    }

    if ($options['cache-get']) {
      // $data might be an object.
      if (is_object($data) && $data->data) {
        $data = $data->data;
      }
      // But $data returned from `drush cache-get --format=json` will be an array.
      elseif (is_array($data) && isset($data['data'])) {
        $data = $data['data'];
      }
      else {
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
   * @command cache-rebuild
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @hidden-option cache-clear
   * @aliases cr,rebuild
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_SITE
   */
  public function rebuild($options = ['cache-clear' => TRUE]) {
    if (!drush_get_option('cache-clear', TRUE)) {
      drush_log(dt("Skipping cache-clear operation due to --cache-clear=0 option."), LogLevel::OK);
      return TRUE;
    }
    chdir(DRUPAL_ROOT);

    // We no longer clear APC and similar caches as they are useless on CLI.
    // See https://github.com/drush-ops/drush/pull/2450

    $autoloader = drush_drupal_load_autoloader(DRUPAL_ROOT);
    require_once DRUSH_DRUPAL_CORE . '/includes/utility.inc';

    $request = Request::createFromGlobals();
    // Ensure that the HTTP method is set, which does not happen with Request::createFromGlobals().
    $request->setMethod('GET');
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
   * A complete callback for cache-clear.
   */
  function complete() {
    // Bootstrap as far as possible so that Views and others can list their caches.
    drush_bootstrap_max();
    return array('values' => array_keys(drush_cache_clear_types(TRUE)));
  }

  /**
   * @hook validate cache-clear
   */
  function validate(CommandData $commandData) {
    $types = $this->getTypes(drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL));
    $type = $commandData->input()->getArgument('type');
    // Check if the provided type ($type) is a valid cache type.
    if ($type && !array_key_exists($type, $types)) {
      if ($type === 'all' && drush_drupal_major_version() >= 8) {
        throw new \Exception(dt('`cache-clear all` is deprecated for Drupal 8 and later. Please use the `cache-rebuild` command instead.'));
      }
      // If we haven't done a full bootstrap, provide a more
      // specific message with instructions to the user on
      // bootstrapping a Drupal site for more options.
      if (!drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
        $all_types = $this->getTypes(TRUE);
        if (array_key_exists($type, $all_types)) {
          throw new \Exception(dt("'!type' cache requires a working Drupal site to operate on. Use the --root and --uri options, or a site @alias, or cd to a directory containing a Drupal settings.php file.", array('!type' => $type)));
        }
        else {
          throw new \Exception(dt("'!type' cache is not a valid cache type. There may be more cache types available if you select a working Drupal site.", array('!type' => $type)));
        }
      }
      throw new \Exception(dt("'!type' cache is not a valid cache type.", array('!type' => $type)));
    }
  }

  /**
   * Types of caches available for clearing. Contrib commands can hook in their own.
   */
  function getTypes($include_bootstrapped_types = FALSE) {
    drush_include_engine('drupal', 'cache');
    $types = _drush_cache_clear_types($include_bootstrapped_types);

    // Include the appropriate environment engine, so callbacks can use core
    // version specific cache clearing functions directly.
    drush_include_engine('drupal', 'environment');

    // Command files may customize $types as desired.
    $handlers = $this->getCustomEventHandlers('cache-clear', $types, $include_bootstrapped_types);
    foreach ($handlers as $handler) {
      $handler($types, $include_bootstrapped_types);
    }
    return $types;
  }

  /**
   * Clear caches internal to Drush core.
   */
  static function clearDrush() {
    drush_cache_clear_all(NULL, 'default'); // commandfiles, etc.
    drush_cache_clear_all(NULL, 'complete'); // completion
    // Release XML. We don't clear tarballs since those never change.
    $matches = drush_scan_directory(drush_directory_cache('download'), "/^https---updates.drupal.org-release-history/", array('.', '..'));
    array_map('unlink', array_keys($matches));
  }
}