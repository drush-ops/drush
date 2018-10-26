<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\webprofiler\Entity\EntityDecorator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class BlocksDataCollector
 */
class BlocksDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  private $entityManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   */
  public function __construct(EntityTypeManagerInterface $entityManager) {
    $this->entityManager = $entityManager;

    $this->data['blocks']['loaded'] = [];
    $this->data['blocks']['rendered'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $storage = $this->entityManager->getStorage('block');

    $loaded = $this->entityManager->getLoaded('config', 'block');
    $rendered = $this->entityManager->getRendered('block');

    if ($loaded) {
      $this->data['blocks']['loaded'] = $this->getBlocksData($loaded, $storage);

    }

    if ($rendered) {
      $this->data['blocks']['rendered'] = $this->getBlocksData($rendered, $storage);
    }
  }

  /**
   * @return array
   */
  public function getRenderedBlocks() {
    return $this->data['blocks']['rendered'];
  }

  /**
   * @return int
   */
  public function getRenderedBlocksCount() {
    return count($this->getRenderedBlocks());
  }

  /**
   * @return array
   */
  public function getLoadedBlocks() {
    return $this->data['blocks']['loaded'];
  }

  /**
   * @return int
   */
  public function getLoadedBlocksCount() {
    return count($this->getLoadedBlocks());
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'blocks';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Blocks');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Loaded: @loaded, rendered: @rendered', [
      '@loaded' => $this->getLoadedBlocksCount(),
      '@rendered' => $this->getRenderedBlocksCount()
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2hpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDowNDgwMTE3NDA3MjA2ODExOEY2MkNCNjI0NDY3NzkwRCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDowQjg5OTA4OEYwQTgxMUUzQkJDRThFQjA5Q0E1REFCRCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDowQjg5OTA4N0YwQTgxMUUzQkJDRThFQjA5Q0E1REFCRCIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1LjEgTWFjaW50b3NoIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6MDM4MDExNzQwNzIwNjgxMTg3MUZDQ0I0RjY1RTlEM0IiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6MDQ4MDExNzQwNzIwNjgxMThGNjJDQjYyNDQ2Nzc5MEQiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz68h+kGAAAAWElEQVR42mL8//8/A7UBEwMNwAg3lAVEODg4UCW2Dhw4wDgapgyMtEj8eCMKFPCkyI1GFCJMiUnQVDUUX0SNllK4wxRf+JAdUeQUfaMRRd+ib4SHKUCAAQAMcyf8vLAstgAAAABJRU5ErkJggg==';
  }

  /**
   * @param $decorator
   * @param $storage
   *
   * @return array
   */
  private function getBlocksData(EntityDecorator $decorator, EntityStorageInterface $storage) {
    $blocks = [];

    /** @var \Drupal\block\BlockInterface $block */
    foreach ($decorator->getEntities() as $block) {
      /** @var Block $entity */
      if (null !== $block && $entity = $storage->load($block->get('id'))) {

        $route = '';
        if ($entity->hasLinkTemplate('edit-form')) {
          $route = $entity->toUrl('edit-form')->toString();
        }

        $id = $block->get('id');
        $blocks[$id] = [
          'id' => $id,
          'region' => $block->getRegion(),
          'status' => $block->get('status'),
          'theme' => $block->getTheme(),
          'plugin' => $block->get('plugin'),
          'settings' => $block->get('settings'),
          'route' => $route,
        ];
      }
    }

    return $blocks;
  }
}
