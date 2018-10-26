<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\webprofiler\Form\FormBuilderWrapper;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class FormsDataCollector
 */
class FormsDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\webprofiler\Form\FormBuilderWrapper
   */
  private $formBuilder;

  /**
   * @param \Drupal\webprofiler\Form\FormBuilderWrapper $formBuilder
   */
  public function __construct(FormBuilderWrapper $formBuilder) {
    $this->formBuilder = $formBuilder;

    $this->data['forms'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $this->data['forms'] = $this->formBuilder->getBuildForm();
  }

  /**
   * @return array
   */
  public function getForms() {
    return $this->data['forms'];
  }

  /**
   * @return array
   */
  public function getFormsCount() {
    return count($this->getForms());
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'forms';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Forms');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Rendered: @forms', ['@forms' => $this->getFormsCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAcCAYAAABh2p9gAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo0RkE1QUM1QjkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo0RkE1QUM1QzkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjRGQTVBQzU5OTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjRGQTVBQzVBOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+I7130QAAAKRJREFUeNrsVVsKxCAMzIgH8897eDHxGP55LX+0RmjpYxHdyrLsNl9iknEyExA5Z5oZgibH9wPKVlJrnVNKRwZCkPceQ4AhhOpUjJFaeaXUBRjssrW2FvDZGINS2GV9AQb3rpvCvZWhc24rKpdDmp17P2MKjzIFEAD16vdDi73Xbx2/pelZItnz6oiusneB32b4is0Iw8eUfzBlOiCef/l2LAIMAGACWQCJ5bXFAAAAAElFTkSuQmCC';
  }
}
