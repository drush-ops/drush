<?php

namespace Drupal\devel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Toolbar integration handler.
 */
class ToolbarHandler implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The devel toolbar config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * ToolbarHandler constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree, ConfigFactoryInterface $config_factory, AccountProxyInterface $account) {
    $this->menuLinkTree = $menu_link_tree;
    $this->config = $config_factory->get('devel.toolbar.settings');
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('toolbar.menu_tree'),
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Hook bridge.
   *
   * @return array
   *   The devel toolbar items render array.
   *
   * @see hook_toolbar()
   */
  public function toolbar() {
    $items['devel'] = [
      '#cache' => [
        'contexts' => ['user.permissions'],
      ],
    ];

    if ($this->account->hasPermission('access devel information')) {
      $items['devel'] += [
        '#type' => 'toolbar_item',
        '#weight' => 999,
        'tab' => [
          '#type' => 'link',
          '#title' => $this->t('Devel'),
          '#url' => Url::fromRoute('devel.admin_settings'),
          '#attributes' => [
            'title' => $this->t('Development menu'),
            'class' => ['toolbar-icon', 'toolbar-icon-devel'],
          ],
        ],
        'tray' => [
          '#heading' => $this->t('Development menu'),
          'devel_menu' => [
            // Currently devel menu is uncacheable, so instead of poisoning the
            // entire page cache we use a lazy builder.
            // @see \Drupal\devel\Plugin\Menu\DestinationMenuLink
            // @see \Drupal\devel\Plugin\Menu\RouteDetailMenuItem
            '#lazy_builder' => [ToolbarHandler::class . ':lazyBuilder', []],
            // Force the creation of the placeholder instead of rely on the
            // automatical placeholdering or otherwise the page results
            // uncacheable when max-age 0 is bubbled up.
            '#create_placeholder' => TRUE,
          ],
          'configuration' => [
            '#type' => 'link',
            '#title' => $this->t('Configure'),
            '#url' => Url::fromRoute('devel.toolbar.settings_form'),
            '#options' => [
              'attributes' => ['class' => ['edit-devel-toolbar']],
            ],
          ],
        ],
        '#attached' => [
          'library' => 'devel/devel-toolbar',
        ],
      ];
    }

    return $items;
  }

  /**
   * Lazy builder callback for the devel menu toolbar.
   *
   * @return array
   *   The renderable array rapresentation of the devel menu.
   */
  public function lazyBuilder() {
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks()->setTopLevelOnly();

    $tree = $this->menuLinkTree->load('devel', $parameters);

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ['callable' => ToolbarHandler::class . ':processTree'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    $build = $this->menuLinkTree->build($tree);

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($this->config)
      ->applyTo($build);

    return $build;
  }

  /**
   * Adds toolbar-specific attributes to the menu link tree.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function processTree(array $tree) {
    $visible_items = $this->config->get('toolbar_items') ?: [];

    foreach ($tree as $element) {
      $plugin_id = $element->link->getPluginId();
      if (!in_array($plugin_id, $visible_items)) {
        // Add a class that allow to hide the non prioritized menu items when
        // the toolbar has horizontal orientation.
        $element->options['attributes']['class'][] = 'toolbar-horizontal-item-hidden';
      }
    }

    return $tree;
  }

}
