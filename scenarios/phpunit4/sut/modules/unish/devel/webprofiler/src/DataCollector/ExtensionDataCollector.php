<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Defines a data collector for the extension system.
 */
class ExtensionDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param string $root
   *   The app root.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, $root) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->root = $root;

    $this->data['drupal_extension']['modules'] = [];
    $this->data['drupal_extension']['themes'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $modules = $this->moduleHandler->getModuleList();
    $themes = $this->themeHandler->listInfo();

    $this->data['drupal_extension']['count'] = count($modules) + count($themes);
    $this->data['drupal_extension']['modules'] = $modules;
    $this->data['drupal_extension']['themes'] = $themes;
    $this->data['drupal_extension']['installation_path'] = $this->root . '/';
  }

  /**
   * Returns the total number of active extensions.
   *
   * @return int
   */
  public function getExtensionsCount() {
    return isset($this->data['drupal_extension']['count']) ? $this->data['drupal_extension']['count'] : 0;
  }

  /**
   * Returns the total number of active modules.
   *
   * @return int
   */
  public function getModulesCount() {
    return count($this->data['drupal_extension']['modules']);
  }

  /**
   * Returns the total number of active themes.
   *
   * @return int
   */
  public function getThemesCount() {
    return count($this->data['drupal_extension']['themes']);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'drupal_extension';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Extensions');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Total: @extensions', ['@extensions' => $this->getExtensionsCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAcCAYAAABh2p9gAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo0RkE1QUM1RjkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo0RkE1QUM2MDkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjRGQTVBQzVEOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjRGQTVBQzVFOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+7LqYtwAAAUBJREFUeNpi/P//PwM1ARMDlcGogYPQQBYQ4eDgQIza/SClQNwIxA3okgcOHCDZhRVAfA6I64F4EjW8fBLqwn1AnAvE8aQaqAc1QAZJ7DMQRwPxayDuAGIBYgwUAeL1QHwWGm6XgLgUSf4FEPcAsQQQ+xBjICh8AmARBgSCQNwFxEFIatYCMagQcEHXzGhvb09s6bAdiL2gbHYg/oEm/x8Y00ykRAofEvsvzlgGmsoIpIWBWBaIlYD4Bg61B5DY/ED8HRrjIH2yINchh+E7IH4CxPeBuAAam8jgBBD3IfEtgZgTiPdC9T1BySloYCcQ20DTmTg0tuegWVICjZSNWLMeFgBKKsU45EDi9kA8H4ivEmsgNsAKxEXQBH0TiMtwFg5EgvnQXHIFmibfUGrgeaghTdBIxAoYR2u9EWAgQIABAKKeRzEX0gXIAAAAAElFTkSuQmCC';
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $data = $this->data;

    // Copy protected properties over public ones to
    // let json_encode to find them.
    $this->copyToPublic($data['drupal_extension']['modules']);
    $this->copyToPublic($data['drupal_extension']['themes']);

    return $data;
  }

  /**
   * Copies protected properties to public ones.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   */
  private function copyToPublic($extensions) {
    foreach ($extensions as &$extension) {
      $extension->public_type = $extension->getType();
      $extension->public_name = $extension->getName();
      $extension->public_path = $extension->getPath();
      $extension->public_pathname = $extension->getPathname();
      $extension->public_filename = $extension->getFilename();
      $extension->public_extension_pathname = $extension->getExtensionPathname();
      $extension->public_extension_filename = $extension->getExtensionFilename();
    }
  }
}
