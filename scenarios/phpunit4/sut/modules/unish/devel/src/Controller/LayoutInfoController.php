<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns response for Layout Info route.
 */
class LayoutInfoController extends ControllerBase {

  /**
   * The Layout Plugin Manager.
   *
   * @var Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutPluginManager;

  /**
   * LayoutInfoController constructor.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $pluginManagerLayout
   *   The layout manager.
   */
  public function __construct(LayoutPluginManagerInterface $pluginManagerLayout) {
    $this->layoutPluginManager = $pluginManagerLayout;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.core.layout')
    );
  }

  /**
   * Builds the Layout Info page.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function layoutInfoPage() {
    $definedLayouts = [];
    $layouts = $this->layoutPluginManager->getDefinitions();
    foreach ($layouts as $layout) {
      // @todo Revisit once https://www.drupal.org/node/2660124 gets in, getting
      // the image should be as simple as $layout->getIcon().
      $image = NULL;
      if ($layout->getIconPath() != NULL) {
        $image = [
          'data' => [
            '#theme' => 'image',
            '#uri' => $layout->getIconPath(),
            '#alt' => $layout->getLabel(),
            '#height' => '65',
          ]
        ];
      }
      $definedLayouts[] = [
        $image,
        $layout->getLabel(),
        $layout->getDescription(),
        $layout->getCategory(),
        implode(', ', $layout->getRegionLabels()),
        $layout->getProvider(),
      ];
    }

    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('Icon'),
        $this->t('Label'),
        $this->t('Description'),
        $this->t('Category'),
        $this->t('Regions'),
        $this->t('Provider'),
      ],
      '#rows' => $definedLayouts,
      '#empty' => $this->t('No layouts available.'),
      '#attributes' => [
        'class' => ['devel-layout-list'],
      ],
    ];
  }

}
