<?php

/**
 * @file
 * Hooks and documentation related to the theme and render system.
 */

/**
 * @defgroup themeable Theme system overview
 * @{
 * Functions and templates for the user interface that themes can override.
 *
 * Drupal's theme system allows a theme to have nearly complete control over
 * the appearance of the site, which includes both the markup and the CSS used
 * to style the markup. For this system to work, modules, instead of writing
 * HTML markup directly, need to return "render arrays", which are structured
 * hierarchical arrays that include the data to be rendered into HTML (or XML or
 * another output format), and options that affect the markup. Render arrays
 * are ultimately rendered into HTML or other output formats by recursive calls
 * to drupal_render(), traversing the depth of the render array hierarchy. At
 * each level, the theme system is invoked to do the actual rendering. See the
 * documentation of drupal_render() and the
 * @link theme_render Theme system and Render API topic @endlink for more
 * information about render arrays and rendering.
 *
 * @section sec_twig_theme Twig Templating Engine
 * Drupal 8 uses the templating engine Twig. Twig offers developers a fast,
 * secure, and flexible method for building templates for Drupal 8 sites. Twig
 * also offers substantial usability improvements over PHPTemplate, and does
 * not require front-end developers to know PHP to build and manipulate Drupal
 * 8 themes.
 *
 * For further information on theming in Drupal 8 see
 * https://www.drupal.org/docs/8/theming
 *
 * For further Twig documentation see
 * http://twig.sensiolabs.org/doc/templates.html
 *
 * @section sec_theme_hooks Theme Hooks
 * The theme system is invoked in \Drupal\Core\Render\Renderer::doRender() by
 * calling the \Drupal\Core\Theme\ThemeManagerInterface::render() function,
 * which operates on the concept of "theme hooks". Theme hooks define how a
 * particular type of data should be rendered. They are registered by modules by
 * implementing hook_theme(), which specifies the name of the hook, the input
 * "variables" used to provide data and options, and other information. Modules
 * implementing hook_theme() also need to provide a default implementation for
 * each of their theme hooks, normally in a Twig file, and they may also provide
 * preprocessing functions. For example, the core Search module defines a theme
 * hook for a search result item in search_theme():
 * @code
 * return array(
 *   'search_result' => array(
 *     'variables' => array(
 *       'result' => NULL,
 *       'plugin_id' => NULL,
 *     ),
 *    'file' => 'search.pages.inc',
 *   ),
 * );
 * @endcode
 * Given this definition, the template file with the default implementation is
 * search-result.html.twig, which can be found in the
 * core/modules/search/templates directory, and the variables for rendering are
 * the search result and the plugin ID. In addition, there is a function
 * template_preprocess_search_result(), located in file search.pages.inc, which
 * preprocesses the information from the input variables so that it can be
 * rendered by the Twig template; the processed variables that the Twig template
 * receives are documented in the header of the default Twig template file.
 *
 * hook_theme() implementations can also specify that a theme hook
 * implementation is a theme function, but that is uncommon and not recommended.
 * Note that while Twig templates will auto-escape variables, theme functions
 * must explicitly escape any variables by using theme_render_and_autoescape().
 * Failure to do so is likely to result in security vulnerabilities. Theme
 * functions are deprecated in Drupal 8.0.x and will be removed before
 * Drupal 9.0.x. Use Twig templates instead.
 *
 * @section sec_overriding_theme_hooks Overriding Theme Hooks
 * Themes may register new theme hooks within a hook_theme() implementation, but
 * it is more common for themes to override default implementations provided by
 * modules than to register entirely new theme hooks. Themes can override a
 * default implementation by creating a template file with the same name as the
 * default implementation; for example, to override the display of search
 * results, a theme would add a file called search-result.html.twig to its
 * templates directory. A good starting point for doing this is normally to
 * copy the default implementation template, and then modifying it as desired.
 *
 * In the uncommon case that a theme hook uses a theme function instead of a
 * template file, a module would provide a default implementation function
 * called theme_HOOK, where HOOK is the name of the theme hook (for example,
 * theme_search_result() would be the name of the function for search result
 * theming). In this case, a theme can override the default implementation by
 * defining a function called THEME_HOOK() in its THEME.theme file, where THEME
 * is the machine name of the theme (for example, 'bartik' is the machine name
 * of the core Bartik theme, and it would define a function called
 * bartik_search_result() in the bartik.theme file, if the search_result hook
 * implementation was a function instead of a template). Normally, copying the
 * default function is again a good starting point for overriding its behavior.
 * Again, note that theme functions (unlike templates) must explicitly escape
 * variables using theme_render_and_autoescape() or risk security
 * vulnerabilities. Theme functions are deprecated in Drupal 8.0.x and will be
 * removed before Drupal 9.0.x. Use Twig templates instead.
 *
 * @section sec_preprocess_templates Preprocessing for Template Files
 * If the theme implementation is a template file, several functions are called
 * before the template file is invoked to modify the variables that are passed
 * to the template. These make up the "preprocessing" phase, and are executed
 * (if they exist), in the following order (note that in the following list,
 * HOOK indicates the hook being called or a less specific hook. For example, if
 * '#theme' => 'node__article' is called, hook is node__article and node. MODULE
 * indicates a module name, THEME indicates a theme name, and ENGINE indicates a
 * theme engine name). Modules, themes, and theme engines can provide these
 * functions to modify how the data is preprocessed, before it is passed to the
 * theme template:
 * - template_preprocess(&$variables, $hook): Creates a default set of variables
 *   for all theme hooks with template implementations. Provided by Drupal Core.
 * - template_preprocess_HOOK(&$variables): Should be implemented by the module
 *   that registers the theme hook, to set up default variables.
 * - MODULE_preprocess(&$variables, $hook): hook_preprocess() is invoked on all
 *   implementing modules.
 * - MODULE_preprocess_HOOK(&$variables): hook_preprocess_HOOK() is invoked on
 *   all implementing modules, so that modules that didn't define the theme hook
 *   can alter the variables.
 * - ENGINE_engine_preprocess(&$variables, $hook): Allows the theme engine to
 *   set necessary variables for all theme hooks with template implementations.
 * - ENGINE_engine_preprocess_HOOK(&$variables): Allows the theme engine to set
 *   necessary variables for the particular theme hook.
 * - THEME_preprocess(&$variables, $hook): Allows the theme to set necessary
 *   variables for all theme hooks with template implementations.
 * - THEME_preprocess_HOOK(&$variables): Allows the theme to set necessary
 *   variables specific to the particular theme hook.
 *
 * @section sec_preprocess_functions Preprocessing for Theme Functions
 * If the theming implementation is a function, only the theme-hook-specific
 * preprocess functions (the ones ending in _HOOK) are called from the list
 * above. This is because theme hooks with function implementations need to be
 * fast, and calling the non-theme-hook-specific preprocess functions for them
 * would incur a noticeable performance penalty.
 *
 * @section sec_suggestions Theme hook suggestions
 * In some cases, instead of calling the base theme hook implementation (either
 * the default provided by the module that defined the hook, or the override
 * provided by the theme), the theme system will instead look for "suggestions"
 * of other hook names to look for. Suggestions can be specified in several
 * ways:
 * - In a render array, the '#theme' property (which gives the name of the hook
 *   to use) can be an array of theme hook names instead of a single hook name.
 *   In this case, the render system will look first for the highest-priority
 *   hook name, and if no implementation is found, look for the second, and so
 *   on. Note that the highest-priority suggestion is at the end of the array.
 * - In a render array, the '#theme' property can be set to the name of a hook
 *   with a '__SUGGESTION' suffix. For example, in search results theming, the
 *   hook 'item_list__search_results' is given. In this case, the render system
 *   will look for theme templates called item-list--search-results.html.twig,
 *   which would only be used for rendering item lists containing search
 *   results, and if this template is not found, it will fall back to using the
 *   base item-list.html.twig template. This type of suggestion can also be
 *   combined with providing an array of theme hook names as described above.
 * - A module can implement hook_theme_suggestions_HOOK(). This allows the
 *   module that defines the theme template to dynamically return an array
 *   containing specific theme hook names (presumably with '__' suffixes as
 *   defined above) to use as suggestions. For example, the Search module
 *   does this in search_theme_suggestions_search_result() to suggest
 *   search_result__PLUGIN as the theme hook for search result items, where
 *   PLUGIN is the machine name of the particular search plugin type that was
 *   used for the search (such as node_search or user_search).
 *
 * For further information on overriding theme hooks see
 * https://www.drupal.org/node/2186401
 *
 * @section sec_alternate_suggestions Altering theme hook suggestions
 * Modules can also alter the theme suggestions provided using the mechanisms
 * of the previous section. There are two hooks for this: the
 * theme-hook-specific hook_theme_suggestions_HOOK_alter() and the generic
 * hook_theme_suggestions_alter(). These hooks get the current list of
 * suggestions as input, and can change this array (adding suggestions and
 * removing them).
 *
 * @section assets Assets
 * We can distinguish between three types of assets:
 * - Unconditional page-level assets (loaded on all pages where the theme is in
 *   use): these are defined in the theme's *.info.yml file.
 * - Conditional page-level assets (loaded on all pages where the theme is in
 *   use and a certain condition is met): these are attached in
 *   hook_page_attachments_alter(), e.g.:
 *   @code
 *   function THEME_page_attachments_alter(array &$page) {
 *     if ($some_condition) {
 *       $page['#attached']['library'][] = 'mytheme/something';
 *     }
 *   }
 *   @endcode
 * - Template-specific assets (loaded on all pages where a specific template is
 *   in use): these can be added by in preprocessing functions, using @code
 *   $variables['#attached'] @endcode, e.g.:
 *   @code
 *   function THEME_preprocess_menu_local_action(array &$variables) {
 *     // We require Modernizr's touch test for button styling.
 *     $variables['#attached']['library'][] = 'core/modernizr';
 *   }
 *   @endcode
 *
 * @see hooks
 * @see callbacks
 * @see theme_render
 *
 * @}
 */

/**
 * @defgroup theme_render Render API overview
 * @{
 * Overview of the Theme system and Render API.
 *
 * The main purpose of Drupal's Theme system is to give themes complete control
 * over the appearance of the site, which includes the markup returned from HTTP
 * requests and the CSS files used to style that markup. In order to ensure that
 * a theme can completely customize the markup, module developers should avoid
 * directly writing HTML markup for pages, blocks, and other user-visible output
 * in their modules, and instead return structured "render arrays" (see
 * @ref arrays below). Doing this also increases usability, by ensuring that the
 * markup used for similar functionality on different areas of the site is the
 * same, which gives users fewer user interface patterns to learn.
 *
 * For further information on the Theme and Render APIs, see:
 * - https://www.drupal.org/docs/8/theming
 * - https://www.drupal.org/developing/api/8/render
 * - @link themeable Theme system overview @endlink.
 *
 * @section arrays Render arrays
 * The core structure of the Render API is the render array, which is a
 * hierarchical associative array containing data to be rendered and properties
 * describing how the data should be rendered. A render array that is returned
 * by a function to specify markup to be sent to the web browser or other
 * services will eventually be rendered by a call to drupal_render(), which will
 * recurse through the render array hierarchy if appropriate, making calls into
 * the theme system to do the actual rendering. If a function or method actually
 * needs to return rendered output rather than a render array, the best practice
 * would be to create a render array, render it by calling drupal_render(), and
 * return that result, rather than writing the markup directly. See the
 * documentation of drupal_render() for more details of the rendering process.
 *
 * Each level in the hierarchy of a render array (including the outermost array)
 * has one or more array elements. Array elements whose names start with '#' are
 * known as "properties", and the array elements with other names are "children"
 * (constituting the next level of the hierarchy); the names of children are
 * flexible, while property names are specific to the Render API and the
 * particular type of data being rendered. A special case of render arrays is a
 * form array, which specifies the form elements for an HTML form; see the
 * @link form_api Form generation topic @endlink for more information on forms.
 *
 * Render arrays (at any level of the hierarchy) will usually have one of the
 * following properties defined:
 * - #type: Specifies that the array contains data and options for a particular
 *   type of "render element" (for example, 'form', for an HTML form;
 *   'textfield', 'submit', for HTML form element types; 'table', for a table
 *   with rows, columns, and headers). See @ref elements below for more on
 *   render element types.
 * - #theme: Specifies that the array contains data to be themed by a particular
 *   theme hook. Modules define theme hooks by implementing hook_theme(), which
 *   specifies the input "variables" used to provide data and options; if a
 *   hook_theme() implementation specifies variable 'foo', then in a render
 *   array, you would provide this data using property '#foo'. Modules
 *   implementing hook_theme() also need to provide a default implementation for
 *   each of their theme hooks, normally in a Twig file. For more information
 *   and to discover available theme hooks, see the documentation of
 *   hook_theme() and the
 *   @link themeable Default theme implementations topic. @endlink
 * - #markup: Specifies that the array provides HTML markup directly. Unless
 *   the markup is very simple, such as an explanation in a paragraph tag, it
 *   is normally preferable to use #theme or #type instead, so that the theme
 *   can customize the markup. Note that the value is passed through
 *   \Drupal\Component\Utility\Xss::filterAdmin(), which strips known XSS
 *   vectors while allowing a permissive list of HTML tags that are not XSS
 *   vectors. (For example, <script> and <style> are not allowed.) See
 *   \Drupal\Component\Utility\Xss::$adminTags for the list of allowed tags. If
 *   your markup needs any of the tags not in this whitelist, then you can
 *   implement a theme hook and/or an asset library. Alternatively, you can use
 *   the key #allowed_tags to alter which tags are filtered.
 * - #plain_text: Specifies that the array provides text that needs to be
 *   escaped. This value takes precedence over #markup.
 * - #allowed_tags: If #markup is supplied, this can be used to change which
 *   tags are allowed in the markup. The value is an array of tags that
 *   Xss::filter() would accept. If #plain_text is set, this value is ignored.
 *
 *   Usage example:
 *   @code
 *   $output['admin_filtered_string'] = [
 *     '#markup' => '<em>This is filtered using the admin tag list</em>',
 *   ];
 *   $output['filtered_string'] = [
 *     '#markup' => '<video><source src="v.webm" type="video/webm"></video>',
 *     '#allowed_tags' => ['video', 'source'],
 *   ];
 *   $output['escaped_string'] = [
 *     '#plain_text' => '<em>This is escaped</em>',
 *   ];
 *   @endcode
 *
 *   @see core.libraries.yml
 *   @see hook_theme()
 *
 * JavaScript and CSS assets are specified in the render array using the
 * #attached property (see @ref sec_attached).
 *
 * @section elements Render elements
 * Render elements are defined by Drupal core and modules. The primary way to
 * define a render element is to create a render element plugin. There are
 * two types of render element plugins:
 * - Generic elements: Generic render element plugins implement
 *   \Drupal\Core\Render\Element\ElementInterface, are annotated with
 *   \Drupal\Core\Render\Annotation\RenderElement annotation, go in plugin
 *   namespace Element, and generally extend the
 *   \Drupal\Core\Render\Element\RenderElement base class.
 * - Form input elements: Render elements representing form input elements
 *   implement \Drupal\Core\Render\Element\FormElementInterface, are annotated
 *   with \Drupal\Core\Render\Annotation\FormElement annotation, go in plugin
 *   namespace Element, and generally extend the
 *   \Drupal\Core\Render\Element\FormElement base class.
 * See the @link plugin_api Plugin API topic @endlink for general information
 * on plugins. You can search for classes with the RenderElement or FormElement
 * annotation to discover what render elements are available. API reference
 * sites (such as https://api.drupal.org) generate lists of all existing
 * elements from these classes. Look for the Elements link in the API Navigation
 * block.
 *
 * Modules can define render elements by defining an element plugin.
 *
 * @section sec_caching Caching
 * The Drupal rendering process has the ability to cache rendered output at any
 * level in a render array hierarchy. This allows expensive calculations to be
 * done infrequently, and speeds up page loading. See the
 * @link cache Cache API topic @endlink for general information about the cache
 * system.
 *
 * In order to make caching possible, the following information needs to be
 * present:
 * - Cache keys: Identifiers for cacheable portions of render arrays. These
 *   should be created and added for portions of a render array that
 *   involve expensive calculations in the rendering process.
 * - Cache contexts: Contexts that may affect rendering, such as user role and
 *   language. When no context is specified, it means that the render array
 *   does not vary by any context.
 * - Cache tags: Tags for data that rendering depends on, such as for
 *   individual nodes or user accounts, so that when these change the cache
 *   can be automatically invalidated. If the data consists of entities, you
 *   can use \Drupal\Core\Entity\EntityInterface::getCacheTags() to generate
 *   appropriate tags; configuration objects have a similar method.
 * - Cache max-age: The maximum duration for which a render array may be cached.
 *   Defaults to \Drupal\Core\Cache\Cache::PERMANENT (permanently cacheable).
 *
 * Cache information is provided in the #cache property in a render array. In
 * this property, always supply the cache contexts, tags, and max-age if a
 * render array varies by context, depends on some modifiable data, or depends
 * on information that's only valid for a limited time, respectively. Cache keys
 * should only be set on the portions of a render array that should be cached.
 * Contexts are automatically replaced with the value for the current request
 * (e.g. the current language) and combined with the keys to form a cache ID.
 * The cache contexts, tags, and max-age will be propagated up the render array
 * hierarchy to determine cacheability for containing render array sections.
 *
 * Here's an example of what a #cache property might contain:
 * @code
 *   '#cache' => [
 *     'keys' => ['entity_view', 'node', $node->id()],
 *     'contexts' => ['languages'],
 *     'tags' => $node->getCacheTags(),
 *     'max-age' => Cache::PERMANENT,
 *   ],
 * @endcode
 *
 * At the response level, you'll see X-Drupal-Cache-Contexts and
 * X-Drupal-Cache-Tags headers.
 *
 * See https://www.drupal.org/developing/api/8/render/arrays/cacheability for
 * details.
 *
 * @section sec_attached Attaching libraries in render arrays
 * Libraries, JavaScript settings, feeds, HTML <head> tags and HTML <head> links
 * are attached to elements using the #attached property. The #attached property
 * is an associative array, where the keys are the attachment types and the
 * values are the attached data.
 *
 * The #attached property can also be used to specify HTTP headers and the
 * response status code.
 *
 * The #attached property allows loading of asset libraries (which may contain
 * CSS assets, JavaScript assets, and JavaScript setting assets), JavaScript
 * settings, feeds, HTML <head> tags and HTML <head> links. Specify an array of
 * type => value pairs, where the type (most often 'library' — for libraries, or
 * 'drupalSettings' — for JavaScript settings) to attach these response-level
 * values. Example:
 * @code
 * $build['#attached']['library'][] = 'core/jquery';
 * $build['#attached']['drupalSettings']['foo'] = 'bar';
 * $build['#attached']['feed'][] = [$url, $this->t('Feed title')];
 * @endcode
 *
 * See \Drupal\Core\Render\AttachmentsResponseProcessorInterface for additional
 * information.
 *
 * See \Drupal\Core\Asset\LibraryDiscoveryParser::parseLibraryInfo() for more
 * information on how to define libraries.
 *
 * @section sec_placeholders Placeholders in render arrays
 * Render arrays have a placeholder mechanism, which can be used to add data
 * into the render array late in the rendering process. This works in a similar
 * manner to \Drupal\Component\Render\FormattableMarkup::placeholderFormat(),
 * with the text that ends up in the #markup property of the element at the
 * end of the rendering process getting substitutions from placeholders that
 * are stored in the 'placeholders' element of the #attached property.
 *
 * For example, after the rest of the rendering process was done, if your
 * render array contained:
 * @code
 * $build['my_element'] = [
 *   '#attached' => ['placeholders' => ['@foo' => 'replacement']],
 *   '#markup' => ['Something about @foo'],
 * ];
 * @endcode
 * then #markup would end up containing 'Something about replacement'.
 *
 * Note that each placeholder value can itself be a render array, which will be
 * rendered, and any cache tags generated during rendering will be added to the
 * cache tags for the markup.
 *
 * @section render_pipeline The render pipeline
 * The term "render pipeline" refers to the process Drupal uses to take
 * information provided by modules and render it into a response. See
 * https://www.drupal.org/developing/api/8/render for more details on this
 * process. For background on routing concepts, see
 * @link routing Routing API. @endlink
 *
 * There are in fact multiple render pipelines:
 * - Drupal always uses the Symfony render pipeline. See
 *   http://symfony.com/doc/2.7/components/http_kernel/introduction.html
 * - Within the Symfony render pipeline, there is a Drupal render pipeline,
 *   which handles controllers that return render arrays. (Symfony's render
 *   pipeline only knows how to deal with Response objects; this pipeline
 *   converts render arrays into Response objects.) These render arrays are
 *   considered the main content, and can be rendered into multiple formats:
 *   HTML, Ajax, dialog, and modal. Modules can add support for more formats, by
 *   implementing a main content renderer, which is a service tagged with
 *   'render.main_content_renderer'.
 * - Finally, within the HTML main content renderer, there is another pipeline,
 *   to allow for rendering the page containing the main content in multiple
 *   ways: no decoration at all (just a page showing the main content) or blocks
 *   (a page with regions, with blocks positioned in regions around the main
 *   content). Modules can provide additional options, by implementing a page
 *   variant, which is a plugin annotated with
 *   \Drupal\Core\Display\Annotation\PageDisplayVariant.
 *
 * Routes whose controllers return a \Symfony\Component\HttpFoundation\Response
 * object are fully handled by the Symfony render pipeline.
 *
 * Routes whose controllers return the "main content" as a render array can be
 * requested in multiple formats (HTML, JSON, etc.) and/or in a "decorated"
 * manner, as described above.
 *
 * @see themeable
 * @see \Symfony\Component\HttpKernel\KernelEvents::VIEW
 * @see \Drupal\Core\EventSubscriber\MainContentViewSubscriber
 * @see \Drupal\Core\Render\MainContent\MainContentRendererInterface
 * @see \Drupal\Core\Render\MainContent\HtmlRenderer
 * @see \Drupal\Core\Render\RenderEvents::SELECT_PAGE_DISPLAY_VARIANT
 * @see \Drupal\Core\Render\Plugin\DisplayVariant\SimplePageVariant
 * @see \Drupal\block\Plugin\DisplayVariant\BlockPageVariant
 * @see \Drupal\Core\Render\BareHtmlPageRenderer
 *
 * @}
 */

/**
 * @defgroup listing_page_element Page header for Elements page
 * @{
 * Introduction to form and render elements
 *
 * Render elements are referenced in render arrays. Render arrays contain data
 * to be rendered, along with meta-data and attributes that specify how to
 * render the data into markup; see the
 * @link theme_render Render API topic @endlink for an overview of render
 * arrays and render elements. Form arrays are a subset of render arrays,
 * representing HTML forms; form elements are a subset of render elements,
 * representing HTML elements for forms. See the
 * @link form_api Form API topic @endlink for an overview of forms, form
 * processing, and form arrays.
 *
 * Each form and render element type corresponds to an element plugin class;
 * each of them either extends \Drupal\Core\Render\Element\RenderElement
 * (render elements) or \Drupal\Core\Render\Element\FormElement (form
 * elements). Usage and properties are documented on the individual classes,
 * and the two base classes list common properties shared by all render
 * elements and the form element subset, respectively.
 *
 * @see theme_render
 * @see form_api
 * @see \Drupal\Core\Render\Element\RenderElement
 * @see \Drupal\Core\Render\Element\FormElement
 *
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow themes to alter the theme-specific settings form.
 *
 * With this hook, themes can alter the theme-specific settings form in any way
 * allowable by Drupal's Form API, such as adding form elements, changing
 * default values and removing form elements. See the Form API documentation on
 * api.drupal.org for detailed information.
 *
 * Note that the base theme's form alterations will be run before any sub-theme
 * alterations.
 *
 * @param $form
 *   Nested array of form elements that comprise the form.
 * @param $form_state
 *   The current state of the form.
 */
function hook_form_system_theme_settings_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state) {
  // Add a checkbox to toggle the breadcrumb trail.
  $form['toggle_breadcrumb'] = [
    '#type' => 'checkbox',
    '#title' => t('Display the breadcrumb'),
    '#default_value' => theme_get_setting('features.breadcrumb'),
    '#description'   => t('Show a trail of links from the homepage to the current page.'),
  ];
}

/**
 * Preprocess theme variables for templates.
 *
 * This hook allows modules to preprocess theme variables for theme templates.
 * It is called for all theme hooks implemented as templates, but not for theme
 * hooks implemented as functions. hook_preprocess_HOOK() can be used to
 * preprocess variables for a specific theme hook, whether implemented as a
 * template or function.
 *
 * For more detailed information, see the
 * @link themeable Theme system overview topic @endlink.
 *
 * @param $variables
 *   The variables array (modify in place).
 * @param $hook
 *   The name of the theme hook.
 */
function hook_preprocess(&$variables, $hook) {
  static $hooks;

  // Add contextual links to the variables, if the user has permission.

  if (!\Drupal::currentUser()->hasPermission('access contextual links')) {
    return;
  }

  if (!isset($hooks)) {
    $hooks = theme_get_registry();
  }

  // Determine the primary theme function argument.
  if (isset($hooks[$hook]['variables'])) {
    $keys = array_keys($hooks[$hook]['variables']);
    $key = $keys[0];
  }
  else {
    $key = $hooks[$hook]['render element'];
  }

  if (isset($variables[$key])) {
    $element = $variables[$key];
  }

  if (isset($element) && is_array($element) && !empty($element['#contextual_links'])) {
    $variables['title_suffix']['contextual_links'] = contextual_links_view($element);
    if (!empty($variables['title_suffix']['contextual_links'])) {
      $variables['attributes']['class'][] = 'contextual-links-region';
    }
  }
}

/**
 * Preprocess theme variables for a specific theme hook.
 *
 * This hook allows modules to preprocess theme variables for a specific theme
 * hook. It should only be used if a module needs to override or add to the
 * theme preprocessing for a theme hook it didn't define.
 *
 * For more detailed information, see the
 * @link themeable Theme system overview topic @endlink.
 *
 * @param $variables
 *   The variables array (modify in place).
 */
function hook_preprocess_HOOK(&$variables) {
  // This example is from rdf_preprocess_image(). It adds an RDF attribute
  // to the image hook's variables.
  $variables['attributes']['typeof'] = ['foaf:Image'];
}

/**
 * Provides alternate named suggestions for a specific theme hook.
 *
 * This hook allows modules to provide alternative theme function or template
 * name suggestions.
 *
 * HOOK is the least-specific version of the hook being called. For example, if
 * '#theme' => 'node__article' is called, then hook_theme_suggestions_node()
 * will be invoked, not hook_theme_suggestions_node__article(). The specific
 * hook called (in this case 'node__article') is available in
 * $variables['theme_hook_original'].
 *
 * Implementations of this hook must be placed in *.module or *.theme files, or
 * must otherwise make sure that the hook implementation is available at
 * any given time.
 *
 * @todo Add @code sample.
 *
 * @param array $variables
 *   An array of variables passed to the theme hook. Note that this hook is
 *   invoked before any preprocessing.
 *
 * @return array
 *   An array of theme suggestions.
 *
 * @see hook_theme_suggestions_HOOK_alter()
 */
function hook_theme_suggestions_HOOK(array $variables) {
  $suggestions = [];

  $suggestions[] = 'hookname__' . $variables['elements']['#langcode'];

  return $suggestions;
}

/**
 * Alters named suggestions for all theme hooks.
 *
 * This hook is invoked for all theme hooks, if you are targeting a specific
 * theme hook it's best to use hook_theme_suggestions_HOOK_alter().
 *
 * The call order is as follows: all existing suggestion alter functions are
 * called for module A, then all for module B, etc., followed by all for any
 * base theme(s), and finally for the active theme. The order is
 * determined by system weight, then by extension (module or theme) name.
 *
 * Within each module or theme, suggestion alter hooks are called in the
 * following order: first, hook_theme_suggestions_alter(); second,
 * hook_theme_suggestions_HOOK_alter(). So, for each module or theme, the more
 * general hooks are called first followed by the more specific.
 *
 * In the following example, we provide an alternative template suggestion to
 * node and taxonomy term templates based on the user being logged in.
 * @code
 * function MYMODULE_theme_suggestions_alter(array &$suggestions, array $variables, $hook) {
 *   if (\Drupal::currentUser()->isAuthenticated() && in_array($hook, array('node', 'taxonomy_term'))) {
 *     $suggestions[] = $hook . '__' . 'logged_in';
 *   }
 * }
 *
 * @endcode
 *
 * @param array $suggestions
 *   An array of alternate, more specific names for template files or theme
 *   functions.
 * @param array $variables
 *   An array of variables passed to the theme hook. Note that this hook is
 *   invoked before any variable preprocessing.
 * @param string $hook
 *   The base hook name. For example, if '#theme' => 'node__article' is called,
 *   then $hook will be 'node', not 'node__article'. The specific hook called
 *   (in this case 'node__article') is available in
 *   $variables['theme_hook_original'].
 *
 * @return array
 *   An array of theme suggestions.
 *
 * @see hook_theme_suggestions_HOOK_alter()
 */
function hook_theme_suggestions_alter(array &$suggestions, array $variables, $hook) {
  // Add an interface-language specific suggestion to all theme hooks.
  $suggestions[] = $hook . '__' . \Drupal::languageManager()->getCurrentLanguage()->getId();
}

/**
 * Alters named suggestions for a specific theme hook.
 *
 * This hook allows any module or theme to provide alternative theme function or
 * template name suggestions and reorder or remove suggestions provided by
 * hook_theme_suggestions_HOOK() or by earlier invocations of this hook.
 *
 * HOOK is the least-specific version of the hook being called. For example, if
 * '#theme' => 'node__article' is called, then node_theme_suggestions_node()
 * will be invoked, not node_theme_suggestions_node__article(). The specific
 * hook called (in this case 'node__article') is available in
 * $variables['theme_hook_original'].
 *
 * Implementations of this hook must be placed in *.module or *.theme files, or
 * must otherwise make sure that the hook implementation is available at
 * any given time.
 *
 * @todo Add @code sample.
 *
 * @param array $suggestions
 *   An array of theme suggestions.
 * @param array $variables
 *   An array of variables passed to the theme hook. Note that this hook is
 *   invoked before any preprocessing.
 *
 * @see hook_theme_suggestions_alter()
 * @see hook_theme_suggestions_HOOK()
 */
function hook_theme_suggestions_HOOK_alter(array &$suggestions, array $variables) {
  if (empty($variables['header'])) {
    $suggestions[] = 'hookname__' . 'no_header';
  }
}

/**
 * Respond to themes being installed.
 *
 * @param array $theme_list
 *   Array containing the names of the themes being installed.
 *
 * @see \Drupal\Core\Extension\ThemeHandler::install()
 */
function hook_themes_installed($theme_list) {
  foreach ($theme_list as $theme) {
    block_theme_initialize($theme);
  }
}

/**
 * Respond to themes being uninstalled.
 *
 * @param array $themes
 *   Array containing the names of the themes being uninstalled.
 *
 * @see \Drupal\Core\Extension\ThemeHandler::uninstall()
 */
function hook_themes_uninstalled(array $themes) {
  // Remove some state entries depending on the theme.
  foreach ($themes as $theme) {
    \Drupal::state()->delete('example.' . $theme);
  }
}

/**
 * Declare a template file extension to be used with a theme engine.
 *
 * This hook is used in a theme engine implementation in the format of
 * ENGINE_extension().
 *
 * @return string
 *   The file extension the theme engine will recognize.
 */
function hook_extension() {
  // Extension for template base names in Twig.
  return '.html.twig';
}

/**
 * Render a template using the theme engine.
 *
 * It is the theme engine's responsibility to escape variables. The only
 * exception is if a variable implements
 * \Drupal\Component\Render\MarkupInterface. Drupal is inherently unsafe if
 * other variables are not escaped. The helper function
 * theme_render_and_autoescape() may be used for this.
 *
 * @param string $template_file
 *   The path (relative to the Drupal root directory) to the template to be
 *   rendered including its extension in the format 'path/to/TEMPLATE_NAME.EXT'.
 * @param array $variables
 *   A keyed array of variables that are available for composing the output. The
 *   theme engine is responsible for passing all the variables to the template.
 *   Depending on the code in the template, all or just a subset of the
 *   variables might be used in the template.
 *
 * @return string
 *   The output generated from the template. In most cases this will be a string
 *   containing HTML markup.
 */
function hook_render_template($template_file, $variables) {
  $twig_service = \Drupal::service('twig');

  return $twig_service->loadTemplate($template_file)->render($variables);
}

/**
 * Alter the element type information returned from modules.
 *
 * A module may implement this hook in order to alter the element type defaults
 * defined by a module.
 *
 * @param array $info
 *   An associative array with structure identical to that of the return value
 *   of \Drupal\Core\Render\ElementInfoManagerInterface::getInfo().
 *
 * @see \Drupal\Core\Render\ElementInfoManager
 * @see \Drupal\Core\Render\Element\ElementInterface
 */
function hook_element_info_alter(array &$info) {
  // Decrease the default size of textfields.
  if (isset($info['textfield']['#size'])) {
    $info['textfield']['#size'] = 40;
  }
}

/**
 * Perform necessary alterations to the JavaScript before it is presented on
 * the page.
 *
 * @param $javascript
 *   An array of all JavaScript being presented on the page.
 * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
 *   The assets attached to the current response.
 *
 * @see drupal_js_defaults()
 * @see \Drupal\Core\Asset\AssetResolver
 */
function hook_js_alter(&$javascript, \Drupal\Core\Asset\AttachedAssetsInterface $assets) {
  // Swap out jQuery to use an updated version of the library.
  $javascript['core/assets/vendor/jquery/jquery.min.js']['data'] = drupal_get_path('module', 'jquery_update') . '/jquery.js';
}

/**
 * Add dynamic library definitions.
 *
 * Modules may implement this hook to add dynamic library definitions. Static
 * libraries, which do not depend on any runtime information, should be declared
 * in a modulename.libraries.yml file instead.
 *
 * @return array[]
 *   An array of library definitions to register, keyed by library ID. The
 *   library ID will be prefixed with the module name automatically.
 *
 * @see core.libraries.yml
 * @see hook_library_info_alter()
 */
function hook_library_info_build() {
  $libraries = [];
  // Add a library whose information changes depending on certain conditions.
  $libraries['mymodule.zombie'] = [
    'dependencies' => [
      'core/backbone',
    ],
  ];
  if (Drupal::moduleHandler()->moduleExists('minifyzombies')) {
    $libraries['mymodule.zombie'] += [
      'js' => [
        'mymodule.zombie.min.js' => [],
      ],
      'css' => [
        'base' => [
          'mymodule.zombie.min.css' => [],
        ],
      ],
    ];
  }
  else {
    $libraries['mymodule.zombie'] += [
      'js' => [
        'mymodule.zombie.js' => [],
      ],
      'css' => [
        'base' => [
          'mymodule.zombie.css' => [],
        ],
      ],
    ];
  }

  // Add a library only if a certain condition is met. If code wants to
  // integrate with this library it is safe to (try to) load it unconditionally
  // without reproducing this check. If the library definition does not exist
  // the library (of course) not be loaded but no notices or errors will be
  // triggered.
  if (Drupal::moduleHandler()->moduleExists('vampirize')) {
    $libraries['mymodule.vampire'] = [
      'js' => [
        'js/vampire.js' => [],
      ],
      'css' => [
        'base' => [
          'css/vampire.css',
        ],
      ],
      'dependencies' => [
        'core/jquery',
      ],
    ];
  }
  return $libraries;
}

/**
 * Modify the JavaScript settings (drupalSettings).
 *
 * @param array &$settings
 *   An array of all JavaScript settings (drupalSettings) being presented on the
 *   page.
 * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
 *   The assets attached to the current response.
 *
 * @see \Drupal\Core\Asset\AssetResolver
 *
 * The results of this hook are cached, however modules may use
 * hook_js_settings_alter() to dynamically alter settings.
 */
function hook_js_settings_build(array &$settings, \Drupal\Core\Asset\AttachedAssetsInterface $assets) {
  // Manipulate settings.
  if (isset($settings['dialog'])) {
    $settings['dialog']['autoResize'] = FALSE;
  }
}

/**
 * Perform necessary alterations to the JavaScript settings (drupalSettings).
 *
 * @param array &$settings
 *   An array of all JavaScript settings (drupalSettings) being presented on the
 *   page.
 * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
 *   The assets attached to the current response.
 *
 * @see \Drupal\Core\Asset\AssetResolver
 */
function hook_js_settings_alter(array &$settings, \Drupal\Core\Asset\AttachedAssetsInterface $assets) {
  // Add settings.
  $settings['user']['uid'] = \Drupal::currentUser();

  // Manipulate settings.
  if (isset($settings['dialog'])) {
    $settings['dialog']['autoResize'] = FALSE;
  }
}

/**
 * Alter libraries provided by an extension.
 *
 * Allows modules and themes to change libraries' definitions; mostly used to
 * update a library to a newer version, while ensuring backward compatibility.
 * In general, such manipulations should only be done to extend the library's
 * functionality in a backward-compatible way, to avoid breaking other modules
 * and themes that may be using the library.
 *
 * @param array $libraries
 *   An associative array of libraries registered by $extension. Keyed by
 *   internal library name and passed by reference.
 * @param string $extension
 *   Can either be 'core' or the machine name of the extension that registered
 *   the libraries.
 *
 * @see \Drupal\Core\Asset\LibraryDiscoveryParser::parseLibraryInfo()
 */
function hook_library_info_alter(&$libraries, $extension) {
  // Update Farbtastic to version 2.0.
  if ($extension == 'core' && isset($libraries['jquery.farbtastic'])) {
    // Verify existing version is older than the one we are updating to.
    if (version_compare($libraries['jquery.farbtastic']['version'], '2.0', '<')) {
      // Update the existing Farbtastic to version 2.0.
      $libraries['jquery.farbtastic']['version'] = '2.0';
      // To accurately replace library files, the order of files and the options
      // of each file have to be retained; e.g., like this:
      $old_path = 'assets/vendor/farbtastic';
      // Since the replaced library files are no longer located in a directory
      // relative to the original extension, specify an absolute path (relative
      // to DRUPAL_ROOT / base_path()) to the new location.
      $new_path = '/' . drupal_get_path('module', 'farbtastic_update') . '/js';
      $new_js = [];
      $replacements = [
        $old_path . '/farbtastic.js' => $new_path . '/farbtastic-2.0.js',
      ];
      foreach ($libraries['jquery.farbtastic']['js'] as $source => $options) {
        if (isset($replacements[$source])) {
          $new_js[$replacements[$source]] = $options;
        }
        else {
          $new_js[$source] = $options;
        }
      }
      $libraries['jquery.farbtastic']['js'] = $new_js;
    }
  }
}

/**
 * Alter CSS files before they are output on the page.
 *
 * @param $css
 *   An array of all CSS items (files and inline CSS) being requested on the page.
 * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
 *   The assets attached to the current response.
 *
 * @see Drupal\Core\Asset\LibraryResolverInterface::getCssAssets()
 */
function hook_css_alter(&$css, \Drupal\Core\Asset\AttachedAssetsInterface $assets) {
  // Remove defaults.css file.
  unset($css[drupal_get_path('module', 'system') . '/defaults.css']);
}

/**
 * Add attachments (typically assets) to a page before it is rendered.
 *
 * Use this hook when you want to conditionally add attachments to a page.
 *
 * If you want to alter the attachments added by other modules or if your module
 * depends on the elements of other modules, use hook_page_attachments_alter()
 * instead, which runs after this hook.
 *
 * If you try to add anything but #attached and #cache to the array, an
 * exception is thrown.
 *
 * @param array &$attachments
 *   An array that you can add attachments to.
 *
 * @see hook_page_attachments_alter()
 */
function hook_page_attachments(array &$attachments) {
  // Unconditionally attach an asset to the page.
  $attachments['#attached']['library'][] = 'core/domready';

  // Conditionally attach an asset to the page.
  if (!\Drupal::currentUser()->hasPermission('may pet kittens')) {
    $attachments['#attached']['library'][] = 'core/jquery';
  }
}

/**
 * Alter attachments (typically assets) to a page before it is rendered.
 *
 * Use this hook when you want to remove or alter attachments on the page, or
 * add attachments to the page that depend on another module's attachments (this
 * hook runs after hook_page_attachments().
 *
 * If you try to add anything but #attached and #cache to the array, an
 * exception is thrown.
 *
 * @param array &$attachments
 *   Array of all attachments provided by hook_page_attachments() implementations.
 *
 * @see hook_page_attachments()
 */
function hook_page_attachments_alter(array &$attachments) {
  // Conditionally remove an asset.
  if (in_array('core/jquery', $attachments['#attached']['library'])) {
    $index = array_search('core/jquery', $attachments['#attached']['library']);
    unset($attachments['#attached']['library'][$index]);
  }
}

/**
 * Add a renderable array to the top of the page.
 *
 * @param array $page_top
 *   A renderable array representing the top of the page.
 */
function hook_page_top(array &$page_top) {
  $page_top['mymodule'] = ['#markup' => 'This is the top.'];
}

/**
 * Add a renderable array to the bottom of the page.
 *
 * @param array $page_bottom
 *   A renderable array representing the bottom of the page.
 */
function hook_page_bottom(array &$page_bottom) {
  $page_bottom['mymodule'] = ['#markup' => 'This is the bottom.'];
}

/**
 * Register a module or theme's theme implementations.
 *
 * The implementations declared by this hook specify how a particular render
 * array is to be rendered as HTML.
 *
 * @param array $existing
 *   An array of existing implementations that may be used for override
 *   purposes. This is primarily useful for themes that may wish to examine
 *   existing implementations to extract data (such as arguments) so that
 *   it may properly register its own, higher priority implementations.
 * @param $type
 *   Whether a theme, module, etc. is being processed. This is primarily useful
 *   so that themes tell if they are the actual theme being called or a parent
 *   theme. May be one of:
 *   - 'module': A module is being checked for theme implementations.
 *   - 'base_theme_engine': A theme engine is being checked for a theme that is
 *     a parent of the actual theme being used.
 *   - 'theme_engine': A theme engine is being checked for the actual theme
 *     being used.
 *   - 'base_theme': A base theme is being checked for theme implementations.
 *   - 'theme': The actual theme in use is being checked.
 * @param $theme
 *   The actual name of theme, module, etc. that is being being processed.
 * @param $path
 *   The directory path of the theme or module, so that it doesn't need to be
 *   looked up.
 *
 * @return array
 *   An associative array of information about theme implementations. The keys
 *   on the outer array are known as "theme hooks". For theme suggestions,
 *   instead of the array key being the base theme hook, the key is a theme
 *   suggestion name with the format 'base_hook_name__sub_hook_name'.
 *   For render elements, the key is the machine name of the render element.
 *   The array values are themselves arrays containing information about the
 *   theme hook and its implementation. Each information array must contain
 *   either a 'variables' element (for using a #theme element) or a
 *   'render element' element (for render elements), but not both.
 *   The following elements may be part of each information array:
 *   - variables: Only used for #theme in render array: an array of variables,
 *     where the array keys are the names of the variables, and the array
 *     values are the default values if they are not given in the render array.
 *     Template implementations receive each array key as a variable in the
 *     template file (so they must be legal PHP/Twig variable names). Function
 *     implementations are passed the variables in a single $variables function
 *     argument. If you are using these variables in a render array, prefix the
 *     variable names defined here with a #.
 *   - render element: Used for render element items only: the name of the
 *     renderable element or element tree to pass to the theme function. This
 *     name is used as the name of the variable that holds the renderable
 *     element or tree in preprocess and process functions.
 *   - file: The file the implementation resides in. This file will be included
 *     prior to the theme being rendered, to make sure that the function or
 *     preprocess function (as needed) is actually loaded.
 *   - path: Override the path of the file to be used. Ordinarily the module or
 *     theme path will be used, but if the file will not be in the default
 *     path, include it here. This path should be relative to the Drupal root
 *     directory.
 *   - template: If specified, the theme implementation is a template file, and
 *     this is the template name. Do not add 'html.twig' on the end of the
 *     template name. The extension will be added automatically by the default
 *     rendering engine (which is Twig.) If 'path' is specified, 'template'
 *     should also be specified. If neither 'template' nor 'function' are
 *     specified, a default template name will be assumed. For example, if a
 *     module registers the 'search_result' theme hook, 'search-result' will be
 *     assigned as its template name.
 *   - function: (deprecated in Drupal 8.0.x, will be removed in Drupal 9.0.x)
 *     If specified, this will be the function name to invoke for this
 *     implementation. If neither 'template' nor 'function' are specified, a
 *     default template name will be assumed. See above for more details.
 *   - base hook: Used for theme suggestions only: the base theme hook name.
 *     Instead of this suggestion's implementation being used directly, the base
 *     hook will be invoked with this implementation as its first suggestion.
 *     The base hook's files will be included and the base hook's preprocess
 *     functions will be called in addition to any suggestion's preprocess
 *     functions. If an implementation of hook_theme_suggestions_HOOK() (where
 *     HOOK is the base hook) changes the suggestion order, a different
 *     suggestion may be used in place of this suggestion. If after
 *     hook_theme_suggestions_HOOK() this suggestion remains the first
 *     suggestion, then this suggestion's function or template will be used to
 *     generate the rendered output.
 *   - pattern: A regular expression pattern to be used to allow this theme
 *     implementation to have a dynamic name. The convention is to use __ to
 *     differentiate the dynamic portion of the theme. For example, to allow
 *     forums to be themed individually, the pattern might be: 'forum__'. Then,
 *     when the forum is rendered, following render array can be used:
 *     @code
 *     $render_array = array(
 *       '#theme' => array('forum__' . $tid, 'forum'),
 *       '#forum' => $forum,
 *     );
 *     @endcode
 *   - preprocess functions: A list of functions used to preprocess this data.
 *     Ordinarily this won't be used; it's automatically filled in. By default,
 *     for a module this will be filled in as template_preprocess_HOOK. For
 *     a theme this will be filled in as twig_preprocess and
 *     twig_preprocess_HOOK as well as themename_preprocess and
 *     themename_preprocess_HOOK.
 *   - override preprocess functions: Set to TRUE when a theme does NOT want
 *     the standard preprocess functions to run. This can be used to give a
 *     theme FULL control over how variables are set. For example, if a theme
 *     wants total control over how certain variables in the page.html.twig are
 *     set, this can be set to true. Please keep in mind that when this is used
 *     by a theme, that theme becomes responsible for making sure necessary
 *     variables are set.
 *   - type: (automatically derived) Where the theme hook is defined:
 *     'module', 'theme_engine', or 'theme'.
 *   - theme path: (automatically derived) The directory path of the theme or
 *     module, so that it doesn't need to be looked up.
 *
 * @see themeable
 * @see hook_theme_registry_alter()
 */
function hook_theme($existing, $type, $theme, $path) {
  return [
    'forum_display' => [
      'variables' => ['forums' => NULL, 'topics' => NULL, 'parents' => NULL, 'tid' => NULL, 'sortby' => NULL, 'forum_per_page' => NULL],
    ],
    'forum_list' => [
      'variables' => ['forums' => NULL, 'parents' => NULL, 'tid' => NULL],
    ],
    'forum_icon' => [
      'variables' => ['new_posts' => NULL, 'num_posts' => 0, 'comment_mode' => 0, 'sticky' => 0],
    ],
    'status_report' => [
      'render element' => 'requirements',
      'file' => 'system.admin.inc',
    ],
  ];
}

/**
 * Alter the theme registry information returned from hook_theme().
 *
 * The theme registry stores information about all available theme hooks,
 * including which callback functions those hooks will call when triggered,
 * what template files are exposed by these hooks, and so on.
 *
 * Note that this hook is only executed as the theme cache is re-built.
 * Changes here will not be visible until the next cache clear.
 *
 * The $theme_registry array is keyed by theme hook name, and contains the
 * information returned from hook_theme(), as well as additional properties
 * added by \Drupal\Core\Theme\Registry::processExtension().
 *
 * For example:
 * @code
 * $theme_registry['block_content_add_list'] = array (
 *   'template' => 'block-content-add-list',
 *   'path' => 'core/themes/seven/templates',
 *   'type' => 'theme_engine',
 *   'theme path' => 'core/themes/seven',
 *   'includes' => array (
 *     0 => 'core/modules/block_content/block_content.pages.inc',
 *   ),
 *   'variables' => array (
 *     'content' => NULL,
 *   ),
 *   'preprocess functions' => array (
 *     0 => 'template_preprocess',
 *     1 => 'template_preprocess_block_content_add_list',
 *     2 => 'contextual_preprocess',
 *     3 => 'seven_preprocess_block_content_add_list',
 *   ),
 * );
 * @endcode
 *
 * @param $theme_registry
 *   The entire cache of theme registry information, post-processing.
 *
 * @see hook_theme()
 * @see \Drupal\Core\Theme\Registry::processExtension()
 */
function hook_theme_registry_alter(&$theme_registry) {
  // Kill the next/previous forum topic navigation links.
  foreach ($theme_registry['forum_topic_navigation']['preprocess functions'] as $key => $value) {
    if ($value == 'template_preprocess_forum_topic_navigation') {
      unset($theme_registry['forum_topic_navigation']['preprocess functions'][$key]);
    }
  }
}

/**
 * Alter the default, hook-independent variables for all templates.
 *
 * Allows modules to provide additional default template variables or manipulate
 * existing. This hook is invoked from template_preprocess() after basic default
 * template variables have been set up and before the next template preprocess
 * function is invoked.
 *
 * Note that the default template variables are statically cached within a
 * request. When adding a template variable that depends on other context, it is
 * your responsibility to appropriately reset the static cache in
 * template_preprocess() when needed:
 * @code
 * drupal_static_reset('template_preprocess');
 * @endcode
 *
 * See user_template_preprocess_default_variables_alter() for an example.
 *
 * @param array $variables
 *   An associative array of default template variables, as set up by
 *   _template_preprocess_default_variables(). Passed by reference.
 *
 * @see template_preprocess()
 * @see _template_preprocess_default_variables()
 */
function hook_template_preprocess_default_variables_alter(&$variables) {
  $variables['is_admin'] = \Drupal::currentUser()->hasPermission('access administration pages');
}

/**
 * @} End of "addtogroup hooks".
 */
