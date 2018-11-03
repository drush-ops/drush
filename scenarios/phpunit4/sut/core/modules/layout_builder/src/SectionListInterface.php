<?php

namespace Drupal\layout_builder;

/**
 * Defines the interface for an object that stores layout sections.
 *
 * @internal
 *   Layout Builder is currently experimental and should only be leveraged by
 *   experimental modules and development releases of contributed modules.
 *   See https://www.drupal.org/core/experimental for more information.
 *
 * @see \Drupal\layout_builder\Section
 */
interface SectionListInterface extends \Countable {

  /**
   * Gets the layout sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   A sequentially and numerically keyed array of section objects.
   */
  public function getSections();

  /**
   * Gets a domain object for the layout section.
   *
   * @param int $delta
   *   The delta of the section.
   *
   * @return \Drupal\layout_builder\Section
   *   The layout section.
   */
  public function getSection($delta);

  /**
   * Appends a new section to the end of the list.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section to append.
   *
   * @return $this
   */
  public function appendSection(Section $section);

  /**
   * Inserts a new section at a given delta.
   *
   * If a section exists at the given index, the section at that position and
   * others after it are shifted backward.
   *
   * @param int $delta
   *   The delta of the section.
   * @param \Drupal\layout_builder\Section $section
   *   The section to insert.
   *
   * @return $this
   */
  public function insertSection($delta, Section $section);

  /**
   * Removes the section at the given delta.
   *
   * As sections are stored sequentially and numerically this will re-key every
   * subsequent section, shifting them forward.
   *
   * @param int $delta
   *   The delta of the section.
   *
   * @return $this
   */
  public function removeSection($delta);

}
