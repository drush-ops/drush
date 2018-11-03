<?php

namespace Drupal\views\Annotation;

/**
 * Defines a Plugin annotation object for views access plugins.
 *
 * @see \Drupal\views\Plugin\views\access\AccessPluginBase
 *
 * @ingroup views_access_plugins
 *
 * @Annotation
 */
class ViewsAccess extends ViewsPluginAnnotationBase {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin title used in the views UI.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title = '';

  /**
   * (optional) The short title used in the views UI.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $short_title = '';

  /**
   * A short help string; this is displayed in the views UI.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $help = '';

  /**
   * The types of the display this plugin can be used with.
   *
   * For example the Feed display defines the type 'feed', so only rss style
   * and row plugins can be used in the views UI.
   *
   * @var array
   */
  public $display_types;

  /**
   * The base tables on which this access plugin can be used.
   *
   * If no base table is specified the plugin can be used with all tables.
   *
   * @var array
   */
  public $base;

  /**
   * Whether the plugin should be not selectable in the UI.
   *
   * If set to TRUE, you can still use it via the API in config files.
   *
   * @var bool
   */
  public $no_ui;

}
