<?php

namespace Drupal\webprofiler;

/**
 * Interface DrupalDataCollectorInterface.
 */
interface DrupalDataCollectorInterface {

  /**
   * Returns the datacollector title.
   *
   * @return string
   *   The datacollector title.
   */
  public function getTitle();

  /**
   * Returns the name of the collector.
   *
   * @return string
   *   The collector name.
   */
  public function getName();

  /**
   * Returns the string used in vertical tab summary.
   *
   * @return string
   *   The panel summary.
   */
  public function getPanelSummary();

  /**
   * Returns the collector icon in base64 format.
   *
   * @return string
   *   The collector icon.
   */
  public function getIcon();

  /**
   * Returns true if this datacollector has a detail panel.
   *
   * @return bool
   *   True if datacollector has a detail panel, false otherwise.
   */
  public function hasPanel();

  /**
   * Returns the libraries needed in detail panel.
   *
   * @return array
   *   The render array for detail panel.
   */
  public function getLibraries();

  /**
   * @return array
   */
  public function getDrupalSettings();

  /**
   * @return mixed
   */
  public function getData();
}
