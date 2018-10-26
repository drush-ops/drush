<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Url;
use Drupal\devel\DevelDumperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides route responses for the container info pages.
 */
class ContainerInfoController extends ControllerBase implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  /**
   * The dumper manager service.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $dumper;

  /**
   * ServiceInfoController constructor.
   *
   * @param \Drupal\Core\DrupalKernelInterface $drupalKernel
   *   The drupal kernel.
   * @param \Drupal\devel\DevelDumperManagerInterface $dumper
   *   The dumper manager service.
   */
  public function __construct(DrupalKernelInterface $drupalKernel, DevelDumperManagerInterface $dumper) {
    $this->kernel = $drupalKernel;
    $this->dumper = $dumper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('kernel'),
      $container->get('devel.dumper')
    );
  }

  /**
   * Builds the services overview page.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function serviceList() {
    $headers = [
      $this->t('ID'),
      $this->t('Class'),
      $this->t('Alias'),
      $this->t('Operations'),
    ];

    $rows = [];

    if ($container = $this->kernel->getCachedContainerDefinition()) {
      foreach ($container['services'] as $service_id => $definition) {
        $service = unserialize($definition);

        $row['id'] = [
          'data' => $service_id,
          'class' => 'table-filter-text-source',
        ];
        $row['class'] = [
          'data' => isset($service['class']) ? $service['class'] : '',
          'class' => 'table-filter-text-source',
        ];
        $row['alias'] = [
          'data' => array_search($service_id, $container['aliases']) ?: '',
          'class' => 'table-filter-text-source',
        ];
        $row['operations']['data'] = [
          '#type' => 'operations',
          '#links' => [
            'devel' => [
              'title' => $this->t('Devel'),
              'url' => Url::fromRoute('devel.container_info.service.detail', ['service_id' => $service_id]),
            ],
          ],
        ];

        $rows[$service_id] = $row;
      }

      ksort($rows);
    }

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
      '#placeholder' => $this->t('Enter service id, alias or class'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '.devel-filter-text',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the service id, service alias or class to filter by.'),
      ],
    ];
    $output['services'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No services found.'),
      '#sticky' => TRUE,
      '#attributes' => [
        'class' => ['devel-service-list', 'devel-filter-text'],
      ],
    ];

    return $output;
  }

  /**
   * Returns a render array representation of the service.
   *
   * @param string $service_id
   *   The ID of the service to retrieve.
   *
   * @return array
   *   A render array containing the service detail.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the requested service is not defined.
   */
  public function serviceDetail($service_id) {
    $instance = $this->container->get($service_id, ContainerInterface::NULL_ON_INVALID_REFERENCE);
    if ($instance === NULL) {
      throw new NotFoundHttpException();
    }

    $output = [];

    if ($cached_definitions = $this->kernel->getCachedContainerDefinition()) {
      // Tries to retrieve the service definition from the kernel's cached
      // container definition.
      if (isset($cached_definitions['services'][$service_id])) {
        $definition = unserialize($cached_definitions['services'][$service_id]);

        // If the service has an alias add it to the definition.
        if ($alias = array_search($service_id, $cached_definitions['aliases'])) {
          $definition['alias'] = $alias;
        }

        $output['definition'] = $this->dumper->exportAsRenderable($definition, $this->t('Computed Definition'));
      }
    }

    $output['instance'] = $this->dumper->exportAsRenderable($instance, $this->t('Instance'));

    return $output;
  }

  /**
   * Builds the parameters overview page.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function parameterList() {
    $headers = [
      $this->t('Name'),
      $this->t('Operations'),
    ];

    $rows = [];

    if ($container = $this->kernel->getCachedContainerDefinition()) {
      foreach ($container['parameters'] as $parameter_name => $definition) {
        $row['name'] = [
          'data' => $parameter_name,
          'class' => 'table-filter-text-source',
        ];
        $row['operations']['data'] = [
          '#type' => 'operations',
          '#links' => [
            'devel' => [
              'title' => $this->t('Devel'),
              'url' => Url::fromRoute('devel.container_info.parameter.detail', ['parameter_name' => $parameter_name]),
            ],
          ],
        ];

        $rows[$parameter_name] = $row;
      }

      ksort($rows);
    }

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
      '#placeholder' => $this->t('Enter parameter name'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '.devel-filter-text',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the parameter name to filter by.'),
      ],
    ];
    $output['parameters'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No parameters found.'),
      '#sticky' => TRUE,
      '#attributes' => [
        'class' => ['devel-parameter-list', 'devel-filter-text'],
      ],
    ];

    return $output;
  }

  /**
   * Returns a render array representation of the parameter value.
   *
   * @param string $parameter_name
   *   The name of the parameter to retrieve.
   *
   * @return array
   *   A render array containing the parameter value.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the requested parameter is not defined.
   */
  public function parameterDetail($parameter_name) {
    try {
      $parameter = $this->container->getParameter($parameter_name);
    }
    catch (ParameterNotFoundException $e) {
      throw new NotFoundHttpException();
    }

    return $this->dumper->exportAsRenderable($parameter);
  }

}
