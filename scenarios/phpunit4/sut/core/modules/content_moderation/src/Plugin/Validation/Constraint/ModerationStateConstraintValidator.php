<?php

namespace Drupal\content_moderation\Plugin\Validation\Constraint;

use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if a moderation state transition is valid.
 */
class ModerationStateConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The moderation info.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected $stateTransitionValidation;

  /**
   * Creates a new ModerationStateConstraintValidator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\content_moderation\StateTransitionValidationInterface $state_transition_validation
   *   The state transition validation service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_information, AccountInterface $current_user, StateTransitionValidationInterface $state_transition_validation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
    $this->currentUser = $current_user;
    $this->stateTransitionValidation = $state_transition_validation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information'),
      $container->get('current_user'),
      $container->get('content_moderation.state_transition_validation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $value->getEntity();

    // Ignore entities that are not subject to moderation anyway.
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return;
    }

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);

    if (!$workflow->getTypePlugin()->hasState($entity->moderation_state->value)) {
      // If the state we are transitioning to doesn't exist, we can't validate
      // the transitions for this entity further.
      $this->context->addViolation($constraint->invalidStateMessage, [
        '%state' => $entity->moderation_state->value,
        '%workflow' => $workflow->label(),
      ]);
      return;
    }

    $new_state = $workflow->getTypePlugin()->getState($entity->moderation_state->value);
    $original_state = $this->getOriginalOrInitialState($entity);

    // If a new state is being set and there is an existing state, validate
    // there is a valid transition between them.
    if (!$original_state->canTransitionTo($new_state->id())) {
      $this->context->addViolation($constraint->message, [
        '%from' => $original_state->label(),
        '%to' => $new_state->label(),
      ]);
    }
    else {
      // If we're sure the transition exists, make sure the user has permission
      // to use it.
      if (!$this->stateTransitionValidation->isTransitionValid($workflow, $original_state, $new_state, $this->currentUser)) {
        $this->context->addViolation($constraint->invalidTransitionAccess, [
          '%original_state' => $original_state->label(),
          '%new_state' => $new_state->label(),
        ]);
      }
    }
  }

  /**
   * Gets the original or initial state of the given entity.
   *
   * When a state is being validated, the original state is used to validate
   * that a valid transition exists for target state and the user has access
   * to the transition between those two states. If the entity has been
   * moderated before, we can load the original unmodified revision and
   * translation for this state.
   *
   * If the entity is new we need to load the initial state from the workflow.
   * Even if a value was assigned to the moderation_state field, the initial
   * state is used to compute an appropriate transition for the purposes of
   * validation.
   *
   * @return \Drupal\workflows\StateInterface
   *   The original or default moderation state.
   */
  protected function getOriginalOrInitialState(ContentEntityInterface $entity) {
    $state = NULL;
    $workflow_type = $this->moderationInformation->getWorkflowForEntity($entity)->getTypePlugin();
    if (!$entity->isNew() && !$this->isFirstTimeModeration($entity)) {
      $original_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($entity->getLoadedRevisionId());
      if (!$entity->isDefaultTranslation() && $original_entity->hasTranslation($entity->language()->getId())) {
        $original_entity = $original_entity->getTranslation($entity->language()->getId());
      }
      if ($workflow_type->hasState($original_entity->moderation_state->value)) {
        $state = $workflow_type->getState($original_entity->moderation_state->value);
      }
    }
    return $state ?: $workflow_type->getInitialState($entity);
  }

  /**
   * Determines if this entity is being moderated for the first time.
   *
   * If the previous version of the entity has no moderation state, we assume
   * that means it predates the presence of moderation states.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being moderated.
   *
   * @return bool
   *   TRUE if this is the entity's first time being moderated, FALSE otherwise.
   */
  protected function isFirstTimeModeration(EntityInterface $entity) {
    $original_entity = $this->moderationInformation->getLatestRevision($entity->getEntityTypeId(), $entity->id());

    if ($original_entity) {
      $original_id = $original_entity->moderation_state;
    }

    return !($entity->moderation_state && $original_entity && $original_id);
  }

}
