<?php

namespace Drupal\action;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for action forms.
 */
abstract class ActionFormBase extends EntityForm {

  /**
   * The action storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The action entity.
   *
   * @var \Drupal\system\ActionConfigEntityInterface
   */
  protected $entity;

  /**
   * Constructs a new action form.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The action storage.
   */
  public function __construct(EntityStorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->entity->label(),
      '#maxlength' => '255',
      '#description' => $this->t('A unique label for this advanced action. This label will be displayed in the interface of modules that integrate with actions.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#disabled' => !$this->entity->isNew(),
      '#maxlength' => 64,
      '#description' => $this->t('A unique name for this action. It must only contain lowercase letters, numbers and underscores.'),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
    ];
    $form['plugin'] = [
      '#type' => 'value',
      '#value' => $this->entity->get('plugin'),
    ];
    $form['type'] = [
      '#type' => 'value',
      '#value' => $this->entity->getType(),
    ];

    if ($plugin = $this->getPlugin()) {
      $form += $plugin->buildConfigurationForm($form, $form_state);
    }

    return parent::form($form, $form_state);
  }

  /**
   * Determines if the action already exists.
   *
   * @param string $id
   *   The action ID.
   *
   * @return bool
   *   TRUE if the action exists, FALSE otherwise.
   */
  public function exists($id) {
    $action = $this->storage->load($id);
    return !empty($action);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($plugin = $this->getPlugin()) {
      $plugin->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    if ($plugin = $this->getPlugin()) {
      $plugin->submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addStatus($this->t('The action has been successfully saved.'));

    $form_state->setRedirect('entity.action.collection');
  }

  /**
   * Gets the action plugin while ensuring it implements configuration form.
   *
   * @return \Drupal\Core\Action\ActionInterface|\Drupal\Core\Plugin\PluginFormInterface|null
   *   The action plugin, or NULL if it does not implement configuration forms.
   */
  protected function getPlugin() {
    if ($this->entity->getPlugin() instanceof PluginFormInterface) {
      return $this->entity->getPlugin();
    }
    return NULL;
  }

}
