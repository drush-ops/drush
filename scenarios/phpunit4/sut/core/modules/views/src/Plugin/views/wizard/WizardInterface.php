<?php

namespace Drupal\views\Plugin\views\wizard;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a common interface for Views Wizard plugins.
 *
 * @ingroup views_wizard_plugins
 */
interface WizardInterface {

  /**
   * Form callback to build other elements in the "show" form.
   *
   * This method builds all form elements beside of the selection of the
   * base table.
   *
   * @param array $form
   *   The full wizard form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the wizard form.
   *
   * @return array
   *   Returns the changed wizard form.
   */
  public function buildForm(array $form, FormStateInterface $form_state);

  /**
   * Validate form and values.
   *
   * @param array $form
   *   The full wizard form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the wizard form.
   *
   * @return array
   *   An empty array if the view is valid; an array of error strings if it is
   *   not.
   */
  public function validateView(array $form, FormStateInterface $form_state);

  /**
   * Creates a view from values that have already been validated.
   *
   * @param array $form
   *   The full wizard form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the wizard form.
   *
   * @return \Drupal\views\ViewEntityInterface
   *   The created view object.
   *
   * @throws \Drupal\views\Plugin\views\wizard\WizardException
   */
  public function createView(array $form, FormStateInterface $form_state);

}
