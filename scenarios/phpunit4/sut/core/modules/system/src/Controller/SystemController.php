<?php

namespace Drupal\system\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Theme\ThemeAccessCheck;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for System routes.
 */
class SystemController extends ControllerBase {

  /**
   * System Manager Service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * The theme access checker service.
   *
   * @var \Drupal\Core\Theme\ThemeAccessCheck
   */
  protected $themeAccess;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * Constructs a new SystemController.
   *
   * @param \Drupal\system\SystemManager $systemManager
   *   System manager service.
   * @param \Drupal\Core\Theme\ThemeAccessCheck $theme_access
   *   The theme access checker service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   */
  public function __construct(SystemManager $systemManager, ThemeAccessCheck $theme_access, FormBuilderInterface $form_builder, ThemeHandlerInterface $theme_handler, MenuLinkTreeInterface $menu_link_tree) {
    $this->systemManager = $systemManager;
    $this->themeAccess = $theme_access;
    $this->formBuilder = $form_builder;
    $this->themeHandler = $theme_handler;
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('system.manager'),
      $container->get('access_check.theme'),
      $container->get('form_builder'),
      $container->get('theme_handler'),
      $container->get('menu.link_tree')
    );
  }

  /**
   * Provide the administration overview page.
   *
   * @param string $link_id
   *   The ID of the administrative path link for which to display child links.
   *
   * @return array
   *   A renderable array of the administration overview page.
   */
  public function overview($link_id) {
    // Check for status report errors.
    if ($this->systemManager->checkRequirements() && $this->currentUser()->hasPermission('administer site configuration')) {
      $this->messenger()->addError($this->t('One or more problems were detected with your Drupal installation. Check the <a href=":status">status report</a> for more information.', [':status' => $this->url('system.status')]));
    }
    // Load all menu links below it.
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $tree_access_cacheability = new CacheableMetadata();
    $blocks = [];
    foreach ($tree as $key => $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($element->access));

      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      $link = $element->link;
      $block['title'] = $link->getTitle();
      $block['description'] = $link->getDescription();
      $block['content'] = [
        '#theme' => 'admin_block_content',
        '#content' => $this->systemManager->getAdminBlock($link),
      ];

      if (!empty($block['content']['#content'])) {
        $blocks[$key] = $block;
      }
    }

    if ($blocks) {
      ksort($blocks);
      $build = [
        '#theme' => 'admin_page',
        '#blocks' => $blocks,
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }
    else {
      $build = [
        '#markup' => $this->t('You do not have any administrative items.'),
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }
  }

  /**
   * Sets whether the admin menu is in compact mode or not.
   *
   * @param string $mode
   *   Valid values are 'on' and 'off'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function compactPage($mode) {
    user_cookie_save(['admin_compact_mode' => ($mode == 'on')]);
    return $this->redirect('<front>');
  }

  /**
   * Provides a single block from the administration menu as a page.
   */
  public function systemAdminMenuBlockPage() {
    return $this->systemManager->getBlockContents();
  }

  /**
   * Returns a theme listing.
   *
   * @return string
   *   An HTML string of the theme listing page.
   *
   * @todo Move into ThemeController.
   */
  public function themesPage() {
    $config = $this->config('system.theme');
    // Get all available themes.
    $themes = $this->themeHandler->rebuildThemeData();
    uasort($themes, 'system_sort_modules_by_info_name');

    $theme_default = $config->get('default');
    $theme_groups = ['installed' => [], 'uninstalled' => []];
    $admin_theme = $config->get('admin');
    $admin_theme_options = [];

    foreach ($themes as &$theme) {
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      $theme->is_default = ($theme->getName() == $theme_default);
      $theme->is_admin = ($theme->getName() == $admin_theme || ($theme->is_default && $admin_theme == '0'));

      // Identify theme screenshot.
      $theme->screenshot = NULL;
      // Create a list which includes the current theme and all its base themes.
      if (isset($themes[$theme->getName()]->base_themes)) {
        $theme_keys = array_keys($themes[$theme->getName()]->base_themes);
        $theme_keys[] = $theme->getName();
      }
      else {
        $theme_keys = [$theme->getName()];
      }
      // Look for a screenshot in the current theme or in its closest ancestor.
      foreach (array_reverse($theme_keys) as $theme_key) {
        if (isset($themes[$theme_key]) && file_exists($themes[$theme_key]->info['screenshot'])) {
          $theme->screenshot = [
            'uri' => $themes[$theme_key]->info['screenshot'],
            'alt' => $this->t('Screenshot for @theme theme', ['@theme' => $theme->info['name']]),
            'title' => $this->t('Screenshot for @theme theme', ['@theme' => $theme->info['name']]),
            'attributes' => ['class' => ['screenshot']],
          ];
          break;
        }
      }

      if (empty($theme->status)) {
        // Ensure this theme is compatible with this version of core.
        $theme->incompatible_core = !isset($theme->info['core']) || ($theme->info['core'] != \DRUPAL::CORE_COMPATIBILITY);
        // Require the 'content' region to make sure the main page
        // content has a common place in all themes.
        $theme->incompatible_region = !isset($theme->info['regions']['content']);
        $theme->incompatible_php = version_compare(phpversion(), $theme->info['php']) < 0;
        // Confirm that all base themes are available.
        $theme->incompatible_base = (isset($theme->info['base theme']) && !($theme->base_themes === array_filter($theme->base_themes)));
        // Confirm that the theme engine is available.
        $theme->incompatible_engine = isset($theme->info['engine']) && !isset($theme->owner);
      }
      $theme->operations = [];
      if (!empty($theme->status) || !$theme->incompatible_core && !$theme->incompatible_php && !$theme->incompatible_base && !$theme->incompatible_engine) {
        // Create the operations links.
        $query['theme'] = $theme->getName();
        if ($this->themeAccess->checkAccess($theme->getName())) {
          $theme->operations[] = [
            'title' => $this->t('Settings'),
            'url' => Url::fromRoute('system.theme_settings_theme', ['theme' => $theme->getName()]),
            'attributes' => ['title' => $this->t('Settings for @theme theme', ['@theme' => $theme->info['name']])],
          ];
        }
        if (!empty($theme->status)) {
          if (!$theme->is_default) {
            $theme_uninstallable = TRUE;
            if ($theme->getName() == $admin_theme) {
              $theme_uninstallable = FALSE;
            }
            // Check it isn't the base of theme of an installed theme.
            foreach ($theme->required_by as $themename => $dependency) {
              if (!empty($themes[$themename]->status)) {
                $theme_uninstallable = FALSE;
              }
            }
            if ($theme_uninstallable) {
              $theme->operations[] = [
                'title' => $this->t('Uninstall'),
                'url' => Url::fromRoute('system.theme_uninstall'),
                'query' => $query,
                'attributes' => ['title' => $this->t('Uninstall @theme theme', ['@theme' => $theme->info['name']])],
              ];
            }
            $theme->operations[] = [
              'title' => $this->t('Set as default'),
              'url' => Url::fromRoute('system.theme_set_default'),
              'query' => $query,
              'attributes' => ['title' => $this->t('Set @theme as default theme', ['@theme' => $theme->info['name']])],
            ];
          }
          $admin_theme_options[$theme->getName()] = $theme->info['name'];
        }
        else {
          $theme->operations[] = [
            'title' => $this->t('Install'),
            'url' => Url::fromRoute('system.theme_install'),
            'query' => $query,
            'attributes' => ['title' => $this->t('Install @theme theme', ['@theme' => $theme->info['name']])],
          ];
          $theme->operations[] = [
            'title' => $this->t('Install and set as default'),
            'url' => Url::fromRoute('system.theme_set_default'),
            'query' => $query,
            'attributes' => ['title' => $this->t('Install @theme as default theme', ['@theme' => $theme->info['name']])],
          ];
        }
      }

      // Add notes to default and administration theme.
      $theme->notes = [];
      if ($theme->is_default) {
        $theme->notes[] = $this->t('default theme');
      }
      if ($theme->is_admin) {
        $theme->notes[] = $this->t('administration theme');
      }

      // Sort installed and uninstalled themes into their own groups.
      $theme_groups[$theme->status ? 'installed' : 'uninstalled'][] = $theme;
    }

    // There are two possible theme groups.
    $theme_group_titles = [
      'installed' => $this->formatPlural(count($theme_groups['installed']), 'Installed theme', 'Installed themes'),
    ];
    if (!empty($theme_groups['uninstalled'])) {
      $theme_group_titles['uninstalled'] = $this->formatPlural(count($theme_groups['uninstalled']), 'Uninstalled theme', 'Uninstalled themes');
    }

    uasort($theme_groups['installed'], 'system_sort_themes');
    $this->moduleHandler()->alter('system_themes_page', $theme_groups);

    $build = [];
    $build[] = [
      '#theme' => 'system_themes_page',
      '#theme_groups' => $theme_groups,
      '#theme_group_titles' => $theme_group_titles,
    ];
    $build[] = $this->formBuilder->getForm('Drupal\system\Form\ThemeAdminForm', $admin_theme_options);

    return $build;
  }

}
