<?php

namespace Drupal\Core\Theme;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Utility\ThemeRegistry;

/**
 * Defines the theme registry service.
 *
 * @internal
 *
 * Theme registry is expected to be used only internally since every
 * hook_theme() implementation depends on the way this class is built. This
 * class may get new features in minor releases so this class should be
 * considered internal.
 *
 * @todo Replace local $registry variables in methods with $this->registry.
 */
class Registry implements DestructableInterface {

  /**
   * The theme object representing the active theme for this registry.
   *
   * @var \Drupal\Core\Theme\ActiveTheme
   */
  protected $theme;

  /**
   * The lock backend that should be used.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The complete theme registry.
   *
   * @var array
   *   An array of theme registries, keyed by the theme name. Each registry is
   *   an associative array keyed by theme hook names, whose values are
   *   associative arrays containing the aggregated hook definition:
   *   - type: The type of the extension the original theme hook originates
   *     from; e.g., 'module' for theme hook 'node' of Node module.
   *   - name: The name of the extension the original theme hook originates
   *     from; e.g., 'node' for theme hook 'node' of Node module.
   *   - theme path: The effective \Drupal\Core\Theme\ActiveTheme::getPath()
   *      during \Drupal\Core\Theme\ThemeManagerInterface::render(), available
   *      as 'directory' variable in templates. For functions, it should point
   *      to the respective theme. For templates, it should point to the
   *      directory that contains the template.
   *   - includes: (optional) An array of include files to load when the theme
   *     hook is executed by \Drupal\Core\Theme\ThemeManagerInterface::render().
   *   - file: (optional) A filename to add to 'includes', either prefixed with
   *     the value of 'path', or the path of the extension implementing
   *     hook_theme().
   *   In case of a theme base hook, one of the following:
   *   - variables: An associative array whose keys are variable names and whose
   *     values are default values of the variables to use for this theme hook.
   *   - render element: A string denoting the name of the variable name, in
   *     which the render element for this theme hook is provided.
   *   In case of a theme template file:
   *   - path: The path to the template file to use. Defaults to the
   *     subdirectory 'templates' of the path of the extension implementing
   *     hook_theme(); e.g., 'core/modules/node/templates' for Node module.
   *   - template: The basename of the template file to use, without extension
   *     (as the extension is specific to the theme engine). The template file
   *     is in the directory defined by 'path'.
   *   - template_file: A full path and file name to a template file to use.
   *     Allows any extension to override the effective template file.
   *   - engine: The theme engine to use for the template file.
   *   In case of a theme function:
   *   - function: The function name to call to generate the output.
   *   For any registered theme hook, including theme hook suggestions:
   *   - preprocess: An array of theme variable preprocess callbacks to invoke
   *     before invoking final theme variable processors.
   *   - process: An array of theme variable process callbacks to invoke
   *     before invoking the actual theme function or template.
   */
  protected $registry = [];

  /**
   * The cache backend to use for the complete theme registry data.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The module handler to use to load modules.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * An array of incomplete, runtime theme registries, keyed by theme name.
   *
   * @var \Drupal\Core\Utility\ThemeRegistry[]
   */
  protected $runtimeRegistry = [];

  /**
   * Stores whether the registry was already initialized.
   *
   * @var bool
   */
  protected $initialized = FALSE;

  /**
   * The name of the theme for which to construct the registry, if given.
   *
   * @var string|null
   */
  protected $themeName;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The runtime cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $runtimeCache;

  /**
   * Constructs a \Drupal\Core\Theme\Registry object.
   *
   * @param string $root
   *   The app root.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend interface to use for the complete theme registry data.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to use to load modules.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $theme_initialization
   *   The theme initialization.
   * @param string $theme_name
   *   (optional) The name of the theme for which to construct the registry.
   * @param \Drupal\Core\Cache\CacheBackendInterface $runtime_cache
   *   The cache backend interface to use for the runtime theme registry data.
   */
  public function __construct($root, CacheBackendInterface $cache, LockBackendInterface $lock, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, ThemeInitializationInterface $theme_initialization, $theme_name = NULL, CacheBackendInterface $runtime_cache = NULL) {
    $this->root = $root;
    $this->cache = $cache;
    $this->lock = $lock;
    $this->moduleHandler = $module_handler;
    $this->themeName = $theme_name;
    $this->themeHandler = $theme_handler;
    $this->themeInitialization = $theme_initialization;
    $this->runtimeCache = $runtime_cache;
  }

  /**
   * Sets the theme manager.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function setThemeManager(ThemeManagerInterface $theme_manager) {
    $this->themeManager = $theme_manager;
  }

  /**
   * Initializes a theme with a certain name.
   *
   * This function does to much magic, so it should be replaced by another
   * services which holds the current active theme information.
   *
   * @param string $theme_name
   *   (optional) The name of the theme for which to construct the registry.
   */
  protected function init($theme_name = NULL) {
    if ($this->initialized) {
      return;
    }
    // Unless instantiated for a specific theme, use globals.
    if (!isset($theme_name)) {
      $this->theme = $this->themeManager->getActiveTheme();
    }
    // Instead of the active theme, a specific theme was requested.
    else {
      $this->theme = $this->themeInitialization->getActiveThemeByName($theme_name);
      $this->themeInitialization->loadActiveTheme($this->theme);
    }
  }

  /**
   * Returns the complete theme registry from cache or rebuilds it.
   *
   * @return array
   *   The complete theme registry data array.
   *
   * @see Registry::$registry
   */
  public function get() {
    $this->init($this->themeName);
    if (isset($this->registry[$this->theme->getName()])) {
      return $this->registry[$this->theme->getName()];
    }
    if ($cache = $this->cache->get('theme_registry:' . $this->theme->getName())) {
      $this->registry[$this->theme->getName()] = $cache->data;
    }
    else {
      $this->build();
      // Only persist it if all modules are loaded to ensure it is complete.
      if ($this->moduleHandler->isLoaded()) {
        $this->setCache();
      }
    }
    return $this->registry[$this->theme->getName()];
  }

  /**
   * Returns the incomplete, runtime theme registry.
   *
   * @return \Drupal\Core\Utility\ThemeRegistry
   *   A shared instance of the ThemeRegistry class, provides an ArrayObject
   *   that allows it to be accessed with array syntax and isset(), and is more
   *   lightweight than the full registry.
   */
  public function getRuntime() {
    $this->init($this->themeName);
    if (!isset($this->runtimeRegistry[$this->theme->getName()])) {
      $this->runtimeRegistry[$this->theme->getName()] = new ThemeRegistry('theme_registry:runtime:' . $this->theme->getName(), $this->runtimeCache ?: $this->cache, $this->lock, ['theme_registry'], $this->moduleHandler->isLoaded());
    }
    return $this->runtimeRegistry[$this->theme->getName()];
  }

  /**
   * Persists the theme registry in the cache backend.
   */
  protected function setCache() {
    $this->cache->set('theme_registry:' . $this->theme->getName(), $this->registry[$this->theme->getName()], Cache::PERMANENT, ['theme_registry']);
  }

  /**
   * Returns the base hook for a given hook suggestion.
   *
   * @param string $hook
   *   The name of a theme hook whose base hook to find.
   *
   * @return string|false
   *   The name of the base hook or FALSE.
   */
  public function getBaseHook($hook) {
    $this->init($this->themeName);
    $base_hook = $hook;
    // Iteratively strip everything after the last '__' delimiter, until a
    // base hook definition is found. Recursive base hooks of base hooks are
    // not supported, so the base hook must be an original implementation that
    // points to a theme function or template.
    while ($pos = strrpos($base_hook, '__')) {
      $base_hook = substr($base_hook, 0, $pos);
      if (isset($this->registry[$base_hook]['exists'])) {
        break;
      }
    }
    if ($pos !== FALSE && $base_hook !== $hook) {
      return $base_hook;
    }
    return FALSE;
  }

  /**
   * Builds the theme registry cache.
   *
   * Theme hook definitions are collected in the following order:
   * - Modules
   * - Base theme engines
   * - Base themes
   * - Theme engine
   * - Theme
   *
   * All theme hook definitions are essentially just collated and merged in the
   * above order. However, various extension-specific default values and
   * customizations are required; e.g., to record the effective file path for
   * theme template. Therefore, this method first collects all extensions per
   * type, and then dispatches the processing for each extension to
   * processExtension().
   *
   * After completing the collection, modules are allowed to alter it. Lastly,
   * any derived and incomplete theme hook definitions that are hook suggestions
   * for base hooks (e.g., 'block__node' for the base hook 'block') need to be
   * determined based on the full registry and classified as 'base hook'.
   *
   * See the @link themeable Default theme implementations topic @endlink for
   * details.
   *
   * @return \Drupal\Core\Utility\ThemeRegistry
   *   The build theme registry.
   *
   * @see hook_theme_registry_alter()
   */
  protected function build() {
    $cache = [];
    // First, preprocess the theme hooks advertised by modules. This will
    // serve as the basic registry. Since the list of enabled modules is the
    // same regardless of the theme used, this is cached in its own entry to
    // save building it for every theme.
    if ($cached = $this->cache->get('theme_registry:build:modules')) {
      $cache = $cached->data;
    }
    else {
      foreach ($this->moduleHandler->getImplementations('theme') as $module) {
        $this->processExtension($cache, $module, 'module', $module, $this->getPath($module));
      }
      // Only cache this registry if all modules are loaded.
      if ($this->moduleHandler->isLoaded()) {
        $this->cache->set("theme_registry:build:modules", $cache, Cache::PERMANENT, ['theme_registry']);
      }
    }

    // Process each base theme.
    // Ensure that we start with the root of the parents, so that both CSS files
    // and preprocess functions comes first.
    foreach (array_reverse($this->theme->getBaseThemes()) as $base) {
      // If the base theme uses a theme engine, process its hooks.
      $base_path = $base->getPath();
      if ($this->theme->getEngine()) {
        $this->processExtension($cache, $this->theme->getEngine(), 'base_theme_engine', $base->getName(), $base_path);
      }
      $this->processExtension($cache, $base->getName(), 'base_theme', $base->getName(), $base_path);
    }

    // And then the same thing, but for the theme.
    if ($this->theme->getEngine()) {
      $this->processExtension($cache, $this->theme->getEngine(), 'theme_engine', $this->theme->getName(), $this->theme->getPath());
    }

    // Hooks provided by the theme itself.
    $this->processExtension($cache, $this->theme->getName(), 'theme', $this->theme->getName(), $this->theme->getPath());

    // Discover and add all preprocess functions for theme hook suggestions.
    $this->postProcessExtension($cache, $this->theme);

    // Let modules and themes alter the registry.
    $this->moduleHandler->alter('theme_registry', $cache);
    $this->themeManager->alterForTheme($this->theme, 'theme_registry', $cache);

    // @todo Implement more reduction of the theme registry entry.
    // Optimize the registry to not have empty arrays for functions.
    foreach ($cache as $hook => $info) {
      if (empty($info['preprocess functions'])) {
        unset($cache[$hook]['preprocess functions']);
      }
    }
    $this->registry[$this->theme->getName()] = $cache;

    return $this->registry[$this->theme->getName()];
  }

  /**
   * Process a single implementation of hook_theme().
   *
   * @param array $cache
   *   The theme registry that will eventually be cached; It is an associative
   *   array keyed by theme hooks, whose values are associative arrays
   *   describing the hook:
   *   - 'type': The passed-in $type.
   *   - 'theme path': The passed-in $path.
   *   - 'function': The name of the function generating output for this theme
   *     hook. Either defined explicitly in hook_theme() or, if neither
   *     'function' nor 'template' is defined, then the default theme function
   *     name is used. The default theme function name is the theme hook
   *     prefixed by either 'theme_' for modules or '$name_' for everything
   *     else. If 'function' is defined, 'template' is not used.
   *   - 'template': The filename of the template generating output for this
   *     theme hook. The template is in the directory defined by the 'path' key
   *     of hook_theme() or defaults to "$path/templates".
   *   - 'variables': The variables for this theme hook as defined in
   *     hook_theme(). If there is more than one implementation and 'variables'
   *     is not specified in a later one, then the previous definition is kept.
   *   - 'render element': The renderable element for this theme hook as defined
   *     in hook_theme(). If there is more than one implementation and
   *     'render element' is not specified in a later one, then the previous
   *     definition is kept.
   *   - See the @link themeable Theme system overview topic @endlink for
   *     detailed documentation.
   * @param string $name
   *   The name of the module, theme engine, base theme engine, theme or base
   *   theme implementing hook_theme().
   * @param string $type
   *   One of 'module', 'theme_engine', 'base_theme_engine', 'theme', or
   *   'base_theme'. Unlike regular hooks that can only be implemented by
   *   modules, each of these can implement hook_theme(). This function is
   *   called in aforementioned order and new entries override older ones. For
   *   example, if a theme hook is both defined by a module and a theme, then
   *   the definition in the theme will be used.
   * @param string $theme
   *   The actual name of theme, module, etc. that is being processed.
   * @param string $path
   *   The directory where $name is. For example, modules/system or
   *   themes/bartik.
   *
   * @see \Drupal\Core\Theme\ThemeManagerInterface::render()
   * @see hook_theme()
   * @see \Drupal\Core\Extension\ThemeHandler::listInfo()
   * @see twig_render_template()
   *
   * @throws \BadFunctionCallException
   */
  protected function processExtension(array &$cache, $name, $type, $theme, $path) {
    $result = [];

    $hook_defaults = [
      'variables' => TRUE,
      'render element' => TRUE,
      'pattern' => TRUE,
      'base hook' => TRUE,
    ];

    $module_list = array_keys($this->moduleHandler->getModuleList());

    // Invoke the hook_theme() implementation, preprocess what is returned, and
    // merge it into $cache.
    $function = $name . '_theme';
    if (function_exists($function)) {
      $result = $function($cache, $type, $theme, $path);
      foreach ($result as $hook => $info) {
        // When a theme or engine overrides a module's theme function
        // $result[$hook] will only contain key/value pairs for information being
        // overridden.  Pull the rest of the information from what was defined by
        // an earlier hook.

        // Fill in the type and path of the module, theme, or engine that
        // implements this theme function.
        $result[$hook]['type'] = $type;
        $result[$hook]['theme path'] = $path;

        // If a theme hook has a base hook, mark its preprocess functions always
        // incomplete in order to inherit the base hook's preprocess functions.
        if (!empty($result[$hook]['base hook'])) {
          $result[$hook]['incomplete preprocess functions'] = TRUE;
        }

        if (isset($cache[$hook]['includes'])) {
          $result[$hook]['includes'] = $cache[$hook]['includes'];
        }

        // Load the includes, as they may contain preprocess functions.
        if (isset($info['includes'])) {
          foreach ($info['includes'] as $include_file) {
            include_once $this->root . '/' . $include_file;
          }
        }

        // If the theme implementation defines a file, then also use the path
        // that it defined. Otherwise use the default path. This allows
        // system.module to declare theme functions on behalf of core .include
        // files.
        if (isset($info['file'])) {
          $include_file = isset($info['path']) ? $info['path'] : $path;
          $include_file .= '/' . $info['file'];
          include_once $this->root . '/' . $include_file;
          $result[$hook]['includes'][] = $include_file;
        }

        // A template file is the default implementation for a theme hook, but
        // if the theme hook specifies a function callback instead, check to
        // ensure the function actually exists.
        if (isset($info['function'])) {
          if (!function_exists($info['function'])) {
            throw new \BadFunctionCallException(sprintf(
              'Theme hook "%s" refers to a theme function callback that does not exist: "%s"',
              $hook,
              $info['function']
            ));
          }
        }
        // Provide a default naming convention for 'template' based on the
        // hook used. If the template does not exist, the theme engine used
        // should throw an exception at runtime when attempting to include
        // the template file.
        elseif (!isset($info['template'])) {
          $info['template'] = strtr($hook, '_', '-');
          $result[$hook]['template'] = $info['template'];
        }

        // Prepend the current theming path when none is set. This is required
        // for the default theme engine to know where the template lives.
        if (isset($result[$hook]['template']) && !isset($info['path'])) {
          $result[$hook]['path'] = $path . '/templates';
        }

        // If the default keys are not set, use the default values registered
        // by the module.
        if (isset($cache[$hook])) {
          $result[$hook] += array_intersect_key($cache[$hook], $hook_defaults);
        }

        // Preprocess variables for all theming hooks, whether the hook is
        // implemented as a template or as a function. Ensure they are arrays.
        if (!isset($info['preprocess functions']) || !is_array($info['preprocess functions'])) {
          $info['preprocess functions'] = [];
          $prefixes = [];
          if ($type == 'module') {
            // Default variable preprocessor prefix.
            $prefixes[] = 'template';
            // Add all modules so they can intervene with their own variable
            // preprocessors. This allows them to provide variable preprocessors
            // even if they are not the owner of the current hook.
            $prefixes = array_merge($prefixes, $module_list);
          }
          elseif ($type == 'theme_engine' || $type == 'base_theme_engine') {
            // Theme engines get an extra set that come before the normally
            // named variable preprocessors.
            $prefixes[] = $name . '_engine';
            // The theme engine registers on behalf of the theme using the
            // theme's name.
            $prefixes[] = $theme;
          }
          else {
            // This applies when the theme manually registers their own variable
            // preprocessors.
            $prefixes[] = $name;
          }
          foreach ($prefixes as $prefix) {
            // Only use non-hook-specific variable preprocessors for theming
            // hooks implemented as templates. See the @defgroup themeable
            // topic.
            if (isset($info['template']) && function_exists($prefix . '_preprocess')) {
              $info['preprocess functions'][] = $prefix . '_preprocess';
            }
            if (function_exists($prefix . '_preprocess_' . $hook)) {
              $info['preprocess functions'][] = $prefix . '_preprocess_' . $hook;
            }
          }
        }
        // Check for the override flag and prevent the cached variable
        // preprocessors from being used. This allows themes or theme engines
        // to remove variable preprocessors set earlier in the registry build.
        if (!empty($info['override preprocess functions'])) {
          // Flag not needed inside the registry.
          unset($result[$hook]['override preprocess functions']);
        }
        elseif (isset($cache[$hook]['preprocess functions']) && is_array($cache[$hook]['preprocess functions'])) {
          $info['preprocess functions'] = array_merge($cache[$hook]['preprocess functions'], $info['preprocess functions']);
        }
        $result[$hook]['preprocess functions'] = $info['preprocess functions'];

        // If a theme implementation definition provides both 'template' and
        // 'function', the 'function' will be used. In this case, if the new
        // result provides a 'template' value, any existing 'function' value
        // must be removed for the override to be called.
        if (isset($result[$hook]['template'])) {
          unset($cache[$hook]['function']);
        }
      }

      // Merge the newly created theme hooks into the existing cache.
      $cache = NestedArray::mergeDeep($cache, $result);
    }

    // Let themes have variable preprocessors even if they didn't register a
    // template.
    if ($type == 'theme' || $type == 'base_theme') {
      foreach ($cache as $hook => $info) {
        // Check only if not registered by the theme or engine.
        if (empty($result[$hook])) {
          if (!isset($info['preprocess functions'])) {
            $cache[$hook]['preprocess functions'] = [];
          }
          // Only use non-hook-specific variable preprocessors for theme hooks
          // implemented as templates. See the @defgroup themeable topic.
          if (isset($info['template']) && function_exists($name . '_preprocess')) {
            $cache[$hook]['preprocess functions'][] = $name . '_preprocess';
          }
          if (function_exists($name . '_preprocess_' . $hook)) {
            $cache[$hook]['preprocess functions'][] = $name . '_preprocess_' . $hook;
            $cache[$hook]['theme path'] = $path;
          }
        }
      }
    }
  }

  /**
   * Completes the definition of the requested suggestion hook.
   *
   * @param string $hook
   *   The name of the suggestion hook to complete.
   * @param array $cache
   *   The theme registry, as documented in
   *   \Drupal\Core\Theme\Registry::processExtension().
   */
  protected function completeSuggestion($hook, array &$cache) {
    $previous_hook = $hook;
    $incomplete_previous_hook = [];
    // Continue looping if the candidate hook doesn't exist or if the candidate
    // hook has incomplete preprocess functions, and if the candidate hook is a
    // suggestion (has a double underscore).
    while ((!isset($cache[$previous_hook]) || isset($cache[$previous_hook]['incomplete preprocess functions']))
      && $pos = strrpos($previous_hook, '__')) {
      // Find the first existing candidate hook that has incomplete preprocess
      // functions.
      if (isset($cache[$previous_hook]) && !$incomplete_previous_hook && isset($cache[$previous_hook]['incomplete preprocess functions'])) {
        $incomplete_previous_hook = $cache[$previous_hook];
        unset($incomplete_previous_hook['incomplete preprocess functions']);
      }
      $previous_hook = substr($previous_hook, 0, $pos);
      $this->mergePreprocessFunctions($hook, $previous_hook, $incomplete_previous_hook, $cache);
    }

    // In addition to processing suggestions, include base hooks.
    if (isset($cache[$hook]['base hook'])) {
      // In order to retain the additions from above, pass in the current hook
      // as the parent hook, otherwise it will be overwritten.
      $this->mergePreprocessFunctions($hook, $cache[$hook]['base hook'], $cache[$hook], $cache);
    }
  }

  /**
   * Merges the source hook's preprocess functions into the destination hook's.
   *
   * @param string $destination_hook_name
   *   The name of the hook to merge preprocess functions to.
   * @param string $source_hook_name
   *   The name of the hook to merge preprocess functions from.
   * @param array $parent_hook
   *   The parent hook if it exists. Either an incomplete hook from suggestions
   *   or a base hook.
   * @param array $cache
   *   The theme registry, as documented in
   *   \Drupal\Core\Theme\Registry::processExtension().
   */
  protected function mergePreprocessFunctions($destination_hook_name, $source_hook_name, $parent_hook, array &$cache) {
    // If base hook exists clone of it for the preprocess function
    // without a template.
    // @see https://www.drupal.org/node/2457295
    if (isset($cache[$source_hook_name]) && (!isset($cache[$source_hook_name]['incomplete preprocess functions']) || !isset($cache[$destination_hook_name]['incomplete preprocess functions']))) {
      $cache[$destination_hook_name] = $parent_hook + $cache[$source_hook_name];
      if (isset($parent_hook['preprocess functions'])) {
        $diff = array_diff($parent_hook['preprocess functions'], $cache[$source_hook_name]['preprocess functions']);
        $cache[$destination_hook_name]['preprocess functions'] = array_merge($cache[$source_hook_name]['preprocess functions'], $diff);
      }
      // If a base hook isn't set, this is the actual base hook.
      if (!isset($cache[$source_hook_name]['base hook'])) {
        $cache[$destination_hook_name]['base hook'] = $source_hook_name;
      }
    }
  }

  /**
   * Completes the theme registry adding discovered functions and hooks.
   *
   * @param array $cache
   *   The theme registry as documented in
   *   \Drupal\Core\Theme\Registry::processExtension().
   * @param \Drupal\Core\Theme\ActiveTheme $theme
   *   Current active theme.
   *
   * @see ::processExtension()
   */
  protected function postProcessExtension(array &$cache, ActiveTheme $theme) {
    // Gather prefixes. This will be used to limit the found functions to the
    // expected naming conventions.
    $prefixes = array_keys((array) $this->moduleHandler->getModuleList());
    foreach (array_reverse($theme->getBaseThemes()) as $base) {
      $prefixes[] = $base->getName();
    }
    if ($theme->getEngine()) {
      $prefixes[] = $theme->getEngine() . '_engine';
    }
    $prefixes[] = $theme->getName();

    $grouped_functions = $this->getPrefixGroupedUserFunctions($prefixes);

    // Collect all variable preprocess functions in the correct order.
    $suggestion_level = [];
    $matches = [];
    // Look for functions named according to the pattern and add them if they
    // have matching hooks in the registry.
    foreach ($prefixes as $prefix) {
      // Grep only the functions which are within the prefix group.
      list($first_prefix,) = explode('_', $prefix, 2);
      if (!isset($grouped_functions[$first_prefix])) {
        continue;
      }
      // Add the function and the name of the associated theme hook to the list
      // of preprocess functions grouped by suggestion specificity if a matching
      // base hook is found.
      foreach ($grouped_functions[$first_prefix] as $candidate) {
        if (preg_match("/^{$prefix}_preprocess_(((?:[^_]++|_(?!_))+)__.*)/", $candidate, $matches)) {
          if (isset($cache[$matches[2]])) {
            $level = substr_count($matches[1], '__');
            $suggestion_level[$level][$candidate] = $matches[1];
          }
        }
      }
    }

    // Add missing variable preprocessors. This is needed for modules that do
    // not explicitly register the hook. For example, when a theme contains a
    // variable preprocess function but it does not implement a template, it
    // will go missing. This will add the expected function. It also allows
    // modules or themes to have a variable process function based on a pattern
    // even if the hook does not exist.
    ksort($suggestion_level);
    foreach ($suggestion_level as $level => $item) {
      foreach ($item as $preprocessor => $hook) {
        if (isset($cache[$hook]['preprocess functions']) && !in_array($hook, $cache[$hook]['preprocess functions'])) {
          // Add missing preprocessor to existing hook.
          $cache[$hook]['preprocess functions'][] = $preprocessor;
        }
        elseif (!isset($cache[$hook]) && strpos($hook, '__')) {
          // Process non-existing hook and register it.
          // Look for a previously defined hook that is either a less specific
          // suggestion hook or the base hook.
          $this->completeSuggestion($hook, $cache);
          $cache[$hook]['preprocess functions'][] = $preprocessor;
        }
      }
    }
    // Inherit all base hook variable preprocess functions into suggestion
    // hooks. This ensures that derivative hooks have a complete set of variable
    // preprocess functions.
    foreach ($cache as $hook => $info) {
      // The 'base hook' is only applied to derivative hooks already registered
      // from a pattern. This is typically set from
      // drupal_find_theme_functions() and drupal_find_theme_templates().
      if (isset($info['incomplete preprocess functions'])) {
        $this->completeSuggestion($hook, $cache);
        unset($cache[$hook]['incomplete preprocess functions']);
      }

      // Optimize the registry.
      if (isset($cache[$hook]['preprocess functions']) && empty($cache[$hook]['preprocess functions'])) {
        unset($cache[$hook]['preprocess functions']);
      }
      // Ensure uniqueness.
      if (isset($cache[$hook]['preprocess functions'])) {
        $cache[$hook]['preprocess functions'] = array_unique($cache[$hook]['preprocess functions']);
      }
    }
  }

  /**
   * Invalidates theme registry caches.
   *
   * To be called when the list of enabled extensions is changed.
   */
  public function reset() {
    // Reset the runtime registry.
    foreach ($this->runtimeRegistry as $runtime_registry) {
      $runtime_registry->clear();
    }
    $this->runtimeRegistry = [];

    $this->registry = [];
    Cache::invalidateTags(['theme_registry']);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->runtimeRegistry as $runtime_registry) {
      $runtime_registry->destruct();
    }
  }

  /**
   * Gets all user functions grouped by the word before the first underscore.
   *
   * @param $prefixes
   *   An array of function prefixes by which the list can be limited.
   * @return array
   *   Functions grouped by the first prefix.
   */
  public function getPrefixGroupedUserFunctions($prefixes = []) {
    $functions = get_defined_functions();

    // If a list of prefixes is supplied, trim down the list to those items
    // only as efficiently as possible.
    if ($prefixes) {
      $theme_functions = preg_grep('/^(' . implode(')|(', $prefixes) . ')_/', $functions['user']);
    }
    else {
      $theme_functions = $functions['user'];
    }

    $grouped_functions = [];
    // Splitting user defined functions into groups by the first prefix.
    foreach ($theme_functions as $function) {
      list($first_prefix,) = explode('_', $function, 2);
      $grouped_functions[$first_prefix][] = $function;
    }

    return $grouped_functions;
  }

  /**
   * Wraps drupal_get_path().
   *
   * @param string $module
   *   The name of the item for which the path is requested.
   *
   * @return string
   */
  protected function getPath($module) {
    return drupal_get_path('module', $module);
  }

}
