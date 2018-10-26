<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Provides a data collector to get all requested state values.
 */
class StateDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
  }

  /**
   * @param $key
   */
  public function addState($key) {
    $this->data['state_get'][$key] = isset($this->data['state_get'][$key]) ? $this->data['state_get'][$key] + 1 : 1;
  }

  /**
   * Twig callback to show all requested state keys.
   */
  public function getStateKeysCount() {
    return count($this->data['state_get']);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('State');
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'state';
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Total: @variables', ['@variables' => $this->getStateKeysCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo2Njc3QTVERTkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo2Njc3QTVERjkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjY2NzdBNURDOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjY2NzdBNUREOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+tji6wwAAAUFJREFUeNpi/P//PwO1ARMDDcDQMZSBgZExE4h/AvF/KuA/QFzKCCS+TZ40iVNeXp5i9718+ZIhNS3tL5OoqChVDAQBcXFxBqBZzDQJU0Z2Do7/FubmVDPwzNmzwymdhoSEgDEM8PDwgPkJCQk49bDgMxCkEWQAyKAXL14wXLhwgWHChAlguTVr1pBnKMiwgoICsEEgC+7cuQPGHR0deH3HzMLC0iAjI4NV8tSpUwzv3r1jYGNjY3BycoJbgg88e/6cuIg6cuQImAZ5n2qx7+HhAaZtbGzArqXYUJCBIAwKR1jMq6ioMBgYGJAeURUVFWCNsHAERRAsKYFofJGF01CQIaBktGPHDjANAjU1NWBXg8IWX/gOobz/8+fP7x8/fqSKYV++fGH4+vXrX1B1EgXET6hUnTwH4jTG0Xqf6gAgwABnDJn0eW/JMwAAAABJRU5ErkJggg==';
  }
}
