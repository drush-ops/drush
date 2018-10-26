<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\devel\DevelDumperManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for devel module routes.
 */
class DevelController extends ControllerBase {

  /**
   * The dumper service.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $dumper;

  /**
   * EntityDebugController constructor.
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
    return new static($container->get('devel.dumper'));
  }

  /**
   * Clears all caches, then redirects to the previous page.
   */
  public function cacheClear() {
    drupal_flush_all_caches();
    drupal_set_message('Cache cleared.');
    return $this->redirect('<front>');
  }

  public function themeRegistry() {
    $hooks = theme_get_registry();
    ksort($hooks);
    return $this->dumper->exportAsRenderable($hooks);
  }

  /**
   * Builds the fields info overview page.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function fieldInfoPage() {
    $fields = FieldStorageConfig::loadMultiple();
    ksort($fields);
    $output['fields'] = $this->dumper->exportAsRenderable($fields, $this->t('Fields'));

    $field_instances = FieldConfig::loadMultiple();
    ksort($field_instances);
    $output['instances'] = $this->dumper->exportAsRenderable($field_instances, $this->t('Instances'));

    $bundles = \Drupal::service('entity_type.bundle.info')->getAllBundleInfo();
    ksort($bundles);
    $output['bundles'] = $this->dumper->exportAsRenderable($bundles, $this->t('Bundles'));

    $field_types = \Drupal::service('plugin.manager.field.field_type')->getUiDefinitions();
    ksort($field_types);
    $output['field_types'] = $this->dumper->exportAsRenderable($field_types, $this->t('Field types'));

    $formatter_types = \Drupal::service('plugin.manager.field.formatter')->getDefinitions();
    ksort($formatter_types);
    $output['formatter_types'] = $this->dumper->exportAsRenderable($formatter_types, $this->t('Formatter types'));

    $widget_types = \Drupal::service('plugin.manager.field.widget')->getDefinitions();
    ksort($widget_types);
    $output['widget_types'] = $this->dumper->exportAsRenderable($widget_types, $this->t('Widget types'));

    return $output;
  }

  /**
   * Builds the state variable overview page.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function stateSystemPage() {
    $output['#attached']['library'][] = 'system/drupal.system.modules';

    $output['filters'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('table-filter', 'js-show'),
      ),
    );

    $output['filters']['text'] = array(
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Enter state name'),
      '#attributes' => array(
        'class' => array('table-filter-text'),
        'data-table' => '.devel-state-list',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the state name to filter by.'),
      ),
    );

    $can_edit = $this->currentUser()->hasPermission('administer site configuration');

    $header = array(
      'name' => $this->t('Name'),
      'value' => $this->t('Value'),
    );

    if ($can_edit) {
      $header['edit'] = $this->t('Operations');
    }

    $rows = array();
    // State class doesn't have getAll method so we get all states from the
    // KeyValueStorage.
    foreach ($this->keyValue('state')->getAll() as $state_name => $state) {
      $rows[$state_name] = array(
        'name' => array(
          'data' => $state_name,
          'class' => 'table-filter-text-source',
        ),
        'value' => array(
          'data' => $this->dumper->export($state),
        ),
      );

      if ($can_edit) {
        $operations['edit'] = array(
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('devel.system_state_edit', array('state_name' => $state_name)),
        );
        $rows[$state_name]['edit'] = array(
          'data' => array('#type' => 'operations', '#links' => $operations),
        );
      }
    }

    $output['states'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No state variables found.'),
      '#attributes' => array(
        'class' => array('devel-state-list'),
      ),
    );

    return $output;
  }

  /**
   * Builds the session overview page.
   *
   * @return array
   *   Array of page elements to render.
   */
  public function session() {
    $output['description'] = array(
      '#markup' => '<p>' . $this->t('Here are the contents of your $_SESSION variable.') . '</p>',
    );
    $output['session'] = array(
      '#type' => 'table',
      '#header' => array($this->t('Session name'), $this->t('Session ID')),
      '#rows' => array(array(session_name(), session_id())),
      '#empty' => $this->t('No session available.'),
    );
    $output['data'] = $this->dumper->exportAsRenderable($_SESSION);

    return $output;
  }

}
