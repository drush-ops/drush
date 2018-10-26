<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Provides a datacollector to show all requested configs.
 */
class ConfigDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
  }

  /**
   * Registers a new requested config name.
   *
   * @param string $name
   *   The name of the config.
   */
  public function addConfigName($name) {
    $this->data['config_names'][$name] = isset($this->data['config_names'][$name]) ? $this->data['config_names'][$name] + 1 : 1;
  }

  /**
   * Callback to display the config names.
   */
  public function getConfigNames() {
    return $this->data['config_names'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'config';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Config');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Total: @count', ['@count' => count($this->getConfigNames())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABsAAAAcCAYAAACQ0cTtAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo2Njc3QTVFMjkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo2Njc3QTVFMzkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjY2NzdBNUUwOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjY2NzdBNUUxOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+f0Re8gAAA3RJREFUeNrsVk9ME1kY/82fTqfQf5ROW2gpuKCioHbdEDeBxITdxE08eOHkiQOr8cZhNx7k4MGDmpAQxTtHPBgPXjCbeLDE/XtglSUGGyhxgBYLdNpSOu3MPN+MwUSQP1HUZONv8st7897M9833fb837zGEEHwusPiM+P86AxjmEqVKSfaBGuWvlHgf6cUUb9+65WhsbPzo706n0/j5wgWddgOUK1vSKEnSvjgyEQwGQW1xtBv+4jXjc7kcBgcH981gJpPZ3tmnjoa7NgOSXwYK2U+bRu76y73XLBaLIRQKvXe8paVlZ0c35L2ncWho6K3B/v5+JBIJOJ3Ot+PmfV9fnzWvcyJWpeNQ/O0oH7oILhK7Q4cnKR9RjlEWrCVtF0Xy/alTW5z19PRgbGwMo6OjlmHToekolUpheHgYhYL1Pso2F+a+OQd/cwTftdlRH9QhujPGconJx5/VyRMvpD+IWrxCa5bmeJ6/GolEtjibmppCuVyGz+dDd3e3ZTwajWJgYMAatyJiBcyGf0J7pwcdzQEcbktBnW8Fr3NMXcOSeLRpKSCw2cD0tNgEQ3+0q0DGx8ettre3FyMjI+/MrYhReKMcgpII1jePdZVBWQXcBxJYfOFGzsjjYGsyErYvdkLJnN1V+hMTE1Zrpm+jv4FVxoemhjISyWqsKi68JDWwcSUsPhWQSQBSdA4Lz+vh92j18rRyZs/rbLMjE0WaTSJlsab+h1LqNES7BqLpYB2vIPwYx/zjNqwlDsBR8VaRwlzzrs42FGlGtgWaBiVZjzLngVChNYQBGATas5OoKCvQSB6k4x64uXYGTwyW322dmao01WgqczNEujMRRYSjqhUOhwAbz4Klu4nB8yhyEtZyPEqh36BquSLYzpltBdLV1WWJwmzN6EzZb4aPUZCfVRHyeiA53Qi6XKhzuxGurUFD8Qz8Xga1BQ+y8SNLEKoe8jupcEOJ26HWSGN5VYYiJ3Ei9i14GpaNezOn8gIcjISn/3Sks8n2PyFqDzhd1y/XeL02URQ/4ExhwFOSMbtux0qxgoDHBb+7GiLPG9l8ce2vyYI8nQjHMXn/Fzy+uWzu1Ofpeze32/D29scVgLpjwMEfSrSVUe1PoUK/Yib+N/69+zuy8gJ9apGxzgcfCkLMmjspq3b4qZsH03XKPPP1kLofeC3AAMaxXzYck+ZeAAAAAElFTkSuQmCC';
  }
}
