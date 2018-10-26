<?php

namespace Drupal\webprofiler\Views;

use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TraceableViewExecutable
 */
class TraceableViewExecutable extends ViewExecutable {

  /**
   * @var float
   */
  protected $render_time;

  /**
   * Gets the build time.
   *
   * @return float
   */
  public function getBuildTime() {
    return $this->build_time;
  }

  /**
   * Gets the execute time.
   *
   * @return float
   */
  public function getExecuteTime() {
    return property_exists($this, 'execute_time') ? $this->execute_time : 0.0;
  }

  /**
   * Gets the render time.
   *
   * @return float
   */
  public function getRenderTime() {
    return $this->render_time;
  }

  /**
   * {@inheritdoc}
   */
  public function render($display_id = NULL) {
    $start = microtime(TRUE);

    $this->execute($display_id);

    // Check to see if the build failed.
    if (!empty($this->build_info['fail'])) {
      return;
    }
    if (!empty($this->build_info['denied'])) {
      return;
    }

    /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_form */
    $exposed_form = $this->display_handler->getPlugin('exposed_form');
    $exposed_form->preRender($this->result);

    $module_handler = \Drupal::moduleHandler();

    // @TODO In the longrun, it would be great to execute a view without
    //   the theme system at all. See https://www.drupal.org/node/2322623.
    $active_theme = \Drupal::theme()->getActiveTheme();
    $themes = array_keys($active_theme->getBaseThemes());
    $themes[] = $active_theme->getName();

    // Check for already-cached output.
    /** @var \Drupal\views\Plugin\views\cache\CachePluginBase $cache */
    if (!empty($this->live_preview)) {
      $cache = Views::pluginManager('cache')->createInstance('none');
    }
    else {
      $cache = $this->display_handler->getPlugin('cache');
    }

    // Run preRender for the pager as it might change the result.
    if (!empty($this->pager)) {
      $this->pager->preRender($this->result);
    }

    // Initialize the style plugin.
    $this->initStyle();

    if (!isset($this->response)) {
      // Set the response so other parts can alter it.
      $this->response = new Response('', 200);
    }

    // Give field handlers the opportunity to perform additional queries
    // using the entire resultset prior to rendering.
    if ($this->style_plugin->usesFields()) {
      foreach ($this->field as $id => $handler) {
        if (!empty($this->field[$id])) {
          $this->field[$id]->preRender($this->result);
        }
      }
    }

    $this->style_plugin->preRender($this->result);

    // Let each area handler have access to the result set.
    $areas = ['header', 'footer'];
    // Only call preRender() on the empty handlers if the result is empty.
    if (empty($this->result)) {
      $areas[] = 'empty';
    }
    foreach ($areas as $area) {
      foreach ($this->{$area} as $handler) {
        $handler->preRender($this->result);
      }
    }

    // Let modules modify the view just prior to rendering it.
    $module_handler->invokeAll('views_pre_render', [$this]);

    // Let the themes play too, because pre render is a very themey thing.
    foreach ($themes as $theme_name) {
      $function = $theme_name . '_views_pre_render';
      if (function_exists($function)) {
        $function($this);
      }
    }

    $this->display_handler->output = $this->display_handler->render();

    $exposed_form->postRender($this->display_handler->output);

    $cache->postRender($this->display_handler->output);

    // Let modules modify the view output after it is rendered.
    $module_handler->invokeAll('views_post_render', [
      $this,
      &$this->display_handler->output,
      $cache
    ]);

    // Let the themes play too, because post render is a very themey thing.
    foreach ($themes as $theme_name) {
      $function = $theme_name . '_views_post_render';
      if (function_exists($function)) {
        $function($this, $this->display_handler->output, $cache);
      }
    }

    $this->render_time = microtime(TRUE) - $start;

    return $this->display_handler->output;
  }
}
