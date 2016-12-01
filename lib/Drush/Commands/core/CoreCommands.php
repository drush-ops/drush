<?php
namespace Drush\Commands\core;

use Drupal;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

class CoreCommands extends DrushCommands {

  /**
   * Run all cron hooks in all active modules for specified site.
   *
   * @command core-cron
   * @aliases cron
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @topics docs-cron
   */
  public function cron() {
    $result = Drupal::service('cron')->run();
    if ($result) {
      $this->logger()->log('Cron run successful.', LogLevel::SUCCESS);
    }
    else {
      throw new \Exception(dt('Cron run failed.'));
    }
  }

  /**
   * Compile all Twig template(s).
   *
   * @command twig-compile
   * @aliases twigc
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function twig_compile() {
    require_once DRUSH_DRUPAL_CORE . "/themes/engines/twig/twig.engine";
    // Scan all enabled modules and themes.
    // @todo refactor since \Drush\Boot\DrupalBoot::commandfile_searchpaths is similar.
    $ignored_modules = drush_get_option_list('ignored-modules', array());
    $cid = drush_cid_install_profile();
    if ($cached = drush_cache_get($cid)) {
      $ignored_modules[] = $cached->data;
    }
    foreach (array_diff(drush_module_list(), $ignored_modules) as $module) {
      $searchpaths[] = drupal_get_path('module', $module);
    }

    $themes = drush_theme_list();
    foreach ($themes as $name => $theme) {
      $searchpaths[] = $theme->getPath();
    }

    foreach ($searchpaths as $searchpath) {
      foreach ($file = drush_scan_directory($searchpath, '/\.html.twig/', array('tests')) as $file) {
        $relative = str_replace(drush_get_context('DRUSH_DRUPAL_ROOT'). '/', '', $file->filename);
        // @todo Dynamically disable twig debugging since there is no good info there anyway.
        twig_render_template($relative, array('theme_hook_original' => ''));
        $this->logger()->info(dt('Compiled twig template !path', array('!path' => $relative)));
      }
    }
  }

  /**
   * Return the filesystem path for modules/themes and other key folders.
   *
   * @command drupal-directory
   * @param string $target A module/theme name, or special names like root, files, private, or an alias : path alias string such as @alias:%files. Defaults to root.
   * @option component The portion of the evaluated path to return.  Defaults to 'path'; 'name' returns the site alias of the target.
   * @option local-only Reject any target that specifies a remote site.
   * @usage cd `drush dd devel`
   *   Navigate into the devel module directory
   * @usage cd `drush dd`
   *   Navigate to the root of your Drupal site
   * @usage cd `drush dd files`
   *   Navigate to the files directory.
   * @usage drush dd @alias:%files
   *   Print the path to the files directory on the site @alias.
   * @usage edit `drush dd devel`/devel.module
   *   Open devel module in your editor (customize 'edit' for your editor)
   * @aliases dd
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   */
  public function drupal_directory($target = 'root', $options = ['component' => 'path', 'local-only' => FALSE]) {
    $path = $this->_drush_core_directory($target, $options['component'], $options['local-only']);

    // If _drush_core_directory is working right, it will turn
    // %blah into the path to the item referred to by the key 'blah'.
    // If there is no such key, then no replacement is done.  In the
    // case of the dd command, we will consider it an error if
    // any keys are -not- replaced in _drush_core_directory.
    if ($path && (strpos($path, '%') === FALSE)) {
      return $path;
    }
    else {
      throw new \Exception(dt("Target '!target' not found.", array('!target' => $target)));
    }
  }

  /**
   * Given a target (e.g. @site:%modules), return the evaluated directory path.
   *
   * @param $target
   *   The target to evaluate.  Can be @site or /path or @site:path
   *   or @site:%pathalias, etc. (just like rsync parameters)
   * @param $component
   *   The portion of the evaluated path to return.  Possible values:
   *   'path' - the full path to the target (default)
   *   'name' - the name of the site from the path (e.g. @site1)
   *   'user-path' - the part after the ':' (e.g. %modules)
   *   'root' & 'uri' - the Drupal root and URI of the site from the path
   *   'path-component' - The ':' and the path
   */
  function _drush_core_directory($target = 'root', $component = 'path', $local_only = FALSE) {
    // Normalize to a sitealias in the target.
    $normalized_target = $target;
    if (strpos($target, ':') === FALSE) {
      if (substr($target, 0, 1) != '@') {
        // drush_sitealias_evaluate_path() requires bootstrap to database.
        if (!drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
          throw new \Exception((dt('You need to specify an alias or run this command within a Drupal site.')));
        }
        $normalized_target = '@self:';
        if (substr($target, 0, 1) != '%') {
          $normalized_target .= '%';
        }
        $normalized_target .= $target;
      }
    }
    $additional_options = array();
    $values = drush_sitealias_evaluate_path($normalized_target, $additional_options, $local_only);
    if (isset($values[$component])) {
      // Hurray, we found the destination.
      return $values[$component];
    }
  }


}