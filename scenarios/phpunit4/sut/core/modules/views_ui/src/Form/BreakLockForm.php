<?php

namespace Drupal\views_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the form to break the lock of an edited view.
 *
 * @internal
 */
class BreakLockForm extends EntityConfirmFormBase {

  /**
   * Stores the Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Stores the shared tempstore.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * Constructs a \Drupal\views_ui\Form\BreakLockForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The Entity manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   */
  public function __construct(EntityManagerInterface $entity_manager, SharedTempStoreFactory $temp_store_factory) {
    $this->entityManager = $entity_manager;
    $this->tempStore = $temp_store_factory->get('views');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('tempstore.shared')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_ui_break_lock_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to break the lock on view %name?', ['%name' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $locked = $this->tempStore->getMetadata($this->entity->id());
    $account = $this->entityManager->getStorage('user')->load($locked->owner);
    $username = [
      '#theme' => 'username',
      '#account' => $account,
    ];
    return $this->t('By breaking this lock, any unsaved changes made by @user will be lost.', ['@user' => \Drupal::service('renderer')->render($username)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->urlInfo('edit-form');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Break lock');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->tempStore->getMetadata($this->entity->id())) {
      $form['message']['#markup'] = $this->t('There is no lock on view %name to break.', ['%name' => $this->entity->id()]);
      return $form;
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->tempStore->delete($this->entity->id());
    $form_state->setRedirectUrl($this->entity->urlInfo('edit-form'));
    $this->messenger()->addStatus($this->t('The lock has been broken and you may now edit this view.'));
  }

}
