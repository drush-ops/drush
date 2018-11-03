<?php

namespace Drupal\path\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\AliasStorageInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the form to delete a path alias.
 *
 * @internal
 */
class DeleteForm extends ConfirmFormBase {

  /**
   * The alias storage service.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * The path alias being deleted.
   *
   * @var array
   */
  protected $pathAlias;

  /**
   * Constructs a \Drupal\path\Form\DeleteForm object.
   *
   * @param \Drupal\Core\Path\AliasStorageInterface $alias_storage
   *   The alias storage service.
   */
  public function __construct(AliasStorageInterface $alias_storage) {
    $this->aliasStorage = $alias_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.alias_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'path_alias_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete path alias %title?', ['%title' => $this->pathAlias['alias']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('path.admin_overview');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pid = NULL) {
    $this->pathAlias = $this->aliasStorage->load(['pid' => $pid]);

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->aliasStorage->delete(['pid' => $this->pathAlias['pid']]);

    $form_state->setRedirect('path.admin_overview');
  }

}
