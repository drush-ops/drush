<?php

namespace Drupal\content_moderation\Entity\Handler;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines operations that need to vary by entity type.
 *
 * Much of the logic contained in this handler is an indication of flaws
 * in the Entity API that are insufficiently standardized between entity types.
 * Hopefully over time functionality can be removed from this interface.
 *
 * @internal
 */
interface ModerationHandlerInterface {

  /**
   * Operates on moderated content entities preSave().
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to modify.
   * @param bool $default_revision
   *   Whether the new revision should be made the default revision.
   * @param bool $published_state
   *   Whether the state being transitioned to is a published state or not.
   */
  public function onPresave(ContentEntityInterface $entity, $default_revision, $published_state);

  /**
   * Alters entity forms to enforce revision handling.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   *
   * @see hook_form_alter()
   */
  public function enforceRevisionsEntityFormAlter(array &$form, FormStateInterface $form_state, $form_id);

  /**
   * Alters bundle forms to enforce revision handling.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   The form id.
   *
   * @see hook_form_alter()
   */
  public function enforceRevisionsBundleFormAlter(array &$form, FormStateInterface $form_state, $form_id);

}
