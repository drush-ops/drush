<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\webprofiler\Views\TraceableViewExecutable;
use Drupal\webprofiler\Views\ViewExecutableFactoryWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects data about rendered views.
 */
class ViewsDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\webprofiler\Views\ViewExecutableFactoryWrapper
   */
  private $view_executable_factory;

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  private $entityManager;

  /**
   * @param ViewExecutableFactoryWrapper $view_executable_factory
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   */
  public function __construct(ViewExecutableFactoryWrapper $view_executable_factory, EntityManagerInterface $entityManager) {
    $this->entityManager = $entityManager;
    $this->view_executable_factory = $view_executable_factory;

    $this->data['views'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $views = $this->view_executable_factory->getViews();
    $storage = $this->entityManager->getStorage('view');

    /** @var TraceableViewExecutable $view */
    foreach ($views as $view) {
      if ($view->executed) {
        $data = [
          'id' => $view->storage->id(),
          'current_display' => $view->current_display,
          'build_time' => $view->getBuildTime(),
          'execute_time' => $view->getExecuteTime(),
          'render_time' => $view->getRenderTime(),
        ];

        $entity = $storage->load($view->storage->id());
        if ($entity->hasLinkTemplate('edit-display-form')) {
          $route = $entity->toUrl('edit-display-form');
          $route->setRouteParameter('display_id', $view->current_display);
          $data['route'] = $route->toString();
        }

        $this->data['views'][] = $data;
      }
    }

//    TODO: also use those data.
//    $loaded = $this->entityManager->getLoaded('view');
//
//    if ($loaded) {
//      /** @var \Drupal\webprofiler\Entity\EntityStorageDecorator $views */
//      foreach ($loaded->getEntities() as $views) {
//        $this->data['views'][] = array(
//          'id' => $views->get('id'),
//        );
//      }
//    }
  }

  /**
   * @return int
   */
  public function getViewsCount() {
    return count($this->data['views']);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'views';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Views');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Total: @count', ['@count' => $this->getViewsCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2hpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDowNDgwMTE3NDA3MjA2ODExOEY2MkNCNjI0NDY3NzkwRCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDozNEVFREM2NkQ4MUMxMUUzQkMwRUNBMkQwMzE4QjVBMyIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDozNEVFREM2NUQ4MUMxMUUzQkMwRUNBMkQwMzE4QjVBMyIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1LjEgTWFjaW50b3NoIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6MDQ4MDExNzQwNzIwNjgxMThGNjJDQjYyNDQ2Nzc5MEQiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6MDQ4MDExNzQwNzIwNjgxMThGNjJDQjYyNDQ2Nzc5MEQiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz6vqYfFAAAAXUlEQVR42mL8//8/A7UBEwMNwKih1AcsIGLz5s1USwK+vr6MLMgcSg2EOW6IhSkycHR0BHth//79jMh8fACmlr4uRbcVnT8apqNhOhqmAxZR1CyoGUfrfaoDgAADAA4QNs9x67RnAAAAAElFTkSuQmCC';
  }
}
