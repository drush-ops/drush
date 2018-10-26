<?php

namespace Drupal\devel\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\devel\DevelDumperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides route responses for the entity types info page.
 */
class EntityTypeInfoController extends ControllerBase {

  /**
   * The dumper service.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $dumper;

  /**
   * EntityTypeInfoController constructor.
   *
   * @param \Drupal\devel\DevelDumperManagerInterface $dumper
   *   The dumper service.
   */
  public function __construct(DevelDumperManagerInterface $dumper) {
    $this->dumper = $dumper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('devel.dumper')
    );
  }

  /**
   * Builds the entity types overview page.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function entityTypeList() {
    $headers = [
      $this->t('ID'),
      $this->t('Name'),
      $this->t('Provider'),
      $this->t('Class'),
      $this->t('Operations'),
    ];

    $rows = [];

    foreach ($this->entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      $row['id'] = [
        'data' => $entity_type->id(),
        'class' => 'table-filter-text-source',
      ];
      $row['name'] = [
        'data' => $entity_type->getLabel(),
        'class' => 'table-filter-text-source',
      ];
      $row['provider'] = [
        'data' => $entity_type->getProvider(),
        'class' => 'table-filter-text-source',
      ];
      $row['class'] = [
        'data' => $entity_type->getClass(),
        'class' => 'table-filter-text-source',
      ];
      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => [
          'devel' => [
            'title' => $this->t('Devel'),
            'url' => Url::fromRoute('devel.entity_info_page.detail', ['entity_type_id' => $entity_type_id]),
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode([
                'width' => 700,
                'minHeight' => 500,
              ]),
            ],
          ],
        ],
      ];

      $rows[$entity_type_id] = $row;
    }

    ksort($rows);

    $output['#attached']['library'][] = 'system/drupal.system.modules';

    $output['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['table-filter', 'js-show'],
      ],
    ];
    $output['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Enter entity type id, provider or class'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '.devel-filter-text',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the entity type id, provider or class to filter by.'),
      ],
    ];
    $output['entities'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No entity types found.'),
      '#sticky' => TRUE,
      '#attributes' => [
        'class' => ['devel-entity-type-list', 'devel-filter-text'],
      ],
    ];

    return $output;
  }

  /**
   * Returns a render array representation of the entity type.
   *
   * @param string $entity_type_id
   *   The name of the entity type to retrieve.
   *
   * @return array
   *   A render array containing the entity type.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the requested entity type is not defined.
   */
  public function entityTypeDetail($entity_type_id) {
    if (!$entity_type = $this->entityTypeManager()->getDefinition($entity_type_id, FALSE)) {
      throw new NotFoundHttpException();
    }

    return $this->dumper->exportAsRenderable($entity_type, $entity_type_id);
  }

}
