<?php

namespace Drupal\comment\Plugin\Menu\LocalTask;

use Drupal\comment\CommentStorageInterface;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a local task that shows the amount of unapproved comments.
 */
class UnapprovedComments extends LocalTaskDefault implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The comment storage service.
   *
   * @var \Drupal\comment\CommentStorageInterface
   */
  protected $commentStorage;

  /**
   * Construct the UnapprovedComments object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\comment\CommentStorageInterface $comment_storage
   *   The comment storage service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, CommentStorageInterface $comment_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->commentStorage = $comment_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')->getStorage('comment')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    return $this->t('Unapproved comments (@count)', ['@count' => $this->commentStorage->getUnapprovedCount()]);
  }

}
