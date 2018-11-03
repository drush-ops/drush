<?php

namespace Drupal\user;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of user entities.
 *
 * @see \Drupal\user\Entity\User
 */
class UserListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Constructs a new UserListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter, RedirectDestinationInterface $redirect_destination) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_query = $this->storage->getQuery();
    $entity_query->condition('uid', 0, '<>');
    $entity_query->pager(50);
    $header = $this->buildHeader();
    $entity_query->tableSort($header);
    $uids = $entity_query->execute();
    return $this->storage->loadMultiple($uids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'username' => [
        'data' => $this->t('Username'),
        'field' => 'name',
        'specifier' => 'name',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'roles' => [
        'data' => $this->t('Roles'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'member_for' => [
        'data' => $this->t('Member for'),
        'field' => 'created',
        'specifier' => 'created',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'access' => [
        'data' => $this->t('Last access'),
        'field' => 'access',
        'specifier' => 'access',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['username']['data'] = [
      '#theme' => 'username',
      '#account' => $entity,
    ];
    $row['status'] = $entity->isActive() ? $this->t('active') : $this->t('blocked');

    $roles = user_role_names(TRUE);
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    $users_roles = [];
    foreach ($entity->getRoles() as $role) {
      if (isset($roles[$role])) {
        $users_roles[] = $roles[$role];
      }
    }
    asort($users_roles);
    $row['roles']['data'] = [
      '#theme' => 'item_list',
      '#items' => $users_roles,
    ];
    $options = [
      'return_as_object' => TRUE,
    ];
    $row['member_for']['data'] = $this->dateFormatter->formatTimeDiffSince($entity->getCreatedTime(), $options)->toRenderable();
    $last_access = $this->dateFormatter->formatTimeDiffSince($entity->getLastAccessedTime(), $options);

    if ($entity->getLastAccessedTime()) {
      $row['access']['data']['#markup'] = $last_access->getString();
      CacheableMetadata::createFromObject($last_access)->applyTo($row['access']['data']);
    }
    else {
      $row['access']['data']['#markup'] = t('never');
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    if (isset($operations['edit'])) {
      $destination = $this->redirectDestination->getAsArray();
      $operations['edit']['query'] = $destination;
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No people available.');
    return $build;
  }

}
