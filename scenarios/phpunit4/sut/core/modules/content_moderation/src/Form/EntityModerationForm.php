<?php

namespace Drupal\content_moderation\Form;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\workflows\Transition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The EntityModerationForm provides a simple UI for changing moderation state.
 *
 * @internal
 */
class EntityModerationForm extends FormBase {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * The moderation state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected $validation;

  /**
   * EntityModerationForm constructor.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param \Drupal\content_moderation\StateTransitionValidationInterface $validation
   *   The moderation state transition validation service.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   */
  public function __construct(ModerationInformationInterface $moderation_info, StateTransitionValidationInterface $validation, Time $time) {
    $this->moderationInfo = $moderation_info;
    $this->validation = $validation;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_moderation_entity_moderation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $entity = NULL) {
    $current_state = $entity->moderation_state->value;
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);

    /** @var \Drupal\workflows\Transition[] $transitions */
    $transitions = $this->validation->getValidTransitions($entity, $this->currentUser());

    // Exclude self-transitions.
    $transitions = array_filter($transitions, function (Transition $transition) use ($current_state) {
      return $transition->to()->id() != $current_state;
    });

    $target_states = [];

    foreach ($transitions as $transition) {
      $target_states[$transition->to()->id()] = $transition->to()->label();
    }

    if (!count($target_states)) {
      return $form;
    }

    if ($current_state) {
      $form['current'] = [
        '#type' => 'item',
        '#title' => $this->t('Moderation state'),
        '#markup' => $workflow->getTypePlugin()->getState($current_state)->label(),
      ];
    }

    // Persist the entity so we can access it in the submit handler.
    $form_state->set('entity', $entity);

    $form['new_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Change to'),
      '#options' => $target_states,
    ];

    $form['revision_log'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Log message'),
      '#size' => 30,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    $form['#theme'] = ['entity_moderation_form'];
    $form['#attached']['library'][] = 'content_moderation/content_moderation';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $entity = $storage->createRevision($entity, $entity->isDefaultRevision());

    $new_state = $form_state->getValue('new_state');

    $entity->set('moderation_state', $new_state);

    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionLogMessage($form_state->getValue('revision_log'));
      $entity->setRevisionUserId($this->currentUser()->id());
    }
    $entity->save();

    $this->messenger()->addStatus($this->t('The moderation state has been updated.'));

    $new_state = $this->moderationInfo->getWorkflowForEntity($entity)->getTypePlugin()->getState($new_state);
    // The page we're on likely won't be visible if we just set the entity to
    // the default state, as we hide that latest-revision tab if there is no
    // pending revision. Redirect to the canonical URL instead, since that will
    // still exist.
    if ($new_state->isDefaultRevisionState()) {
      $form_state->setRedirectUrl($entity->toUrl('canonical'));
    }
  }

}
