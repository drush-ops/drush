<?php

namespace Drupal\webprofiler\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webprofiler\Profiler\ProfilerStorageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Class ConfigForm
 */
class ConfigForm extends ConfigFormBase {

  /**
   * @var \Symfony\Component\HttpKernel\Profiler\Profiler
   */
  private $profiler;

  /**
   * @var array
   */
  private $templates;

  /**
   * @var \Drupal\webprofiler\Profiler\ProfilerStorageManager
   */
  private $storageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('profiler'),
      $container->get('profiler.storage_manager'),
      $container->getParameter('data_collector.templates')
    );
  }

  /**
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param Profiler $profiler
   * @param \Drupal\webprofiler\Profiler\ProfilerStorageManager $storageManager
   * @param array $templates
   */
  public function __construct(ConfigFactoryInterface $config_factory, Profiler $profiler, ProfilerStorageManager $storageManager, $templates) {
    parent::__construct($config_factory);

    $this->profiler = $profiler;
    $this->templates = $templates;
    $this->storageManager = $storageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webprofiler_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('webprofiler.config');

    $form['purge_on_cache_clear'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge on cache clear'),
      '#description' => $this->t('Deletes all profiler files during cache clear.'),
      '#default_value' => $config->get('purge_on_cache_clear'),
    ];

    $storages = $this->storageManager->getStorages();

    $form['storage'] = [
      '#type' => 'select',
      '#title' => $this->t('Storage backend'),
      '#description' => $this->t('Choose were to store profiler data.'),
      '#options' => $storages,
      '#default_value' => $config->get('storage'),
    ];

    $form['exclude'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude'),
      '#default_value' => $config->get('exclude'),
      '#description' => $this->t('Paths to exclude for profiling. One path per line.'),
    ];

    $form['active_toolbar_items'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Active toolbar items'),
      '#options' => $this->getCollectors(),
      '#description' => $this->t('Choose which items to show into the toolbar.'),
      '#default_value' => $config->get('active_toolbar_items'),
    ];

    $form['ide_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('IDE settings'),
      '#open' => FALSE,
    ];

    $form['ide_settings']['ide_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDE link'),
      '#description' => $this->t('IDE link for open files.'),
      '#default_value' => $config->get('ide_link'),
    ];

    $form['ide_settings']['ide_link_remote'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDE link remote path'),
      '#description' => $this->t('The path of the remote docroot. Leave blank if the docroot is on the same machine of the IDE. No trailing slash.'),
      '#default_value' => $config->get('ide_link_remote'),
    ];

    $form['ide_settings']['ide_link_local'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IDE link local path'),
      '#description' => $this->t('The path of the local docroot. Leave blank if the docroot is on the same machine of IDE. No trailing slash.'),
      '#default_value' => $config->get('ide_link_local'),
    ];

    $form['database'] = [
      '#type' => 'details',
      '#title' => $this->t('Database settings'),
      '#open' => FALSE,
      '#states' => array(
        'visible' => array(
          array(
            'input[name="active_toolbar_items[database]"]' => array('checked' => TRUE),
          ),
        ),
      ),
    ];

    $form['database']['query_sort'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sort query log'),
      '#options' => ['source' => 'by source', 'duration' => 'by duration'],
      '#description' => $this->t('The query table can be sorted in the order that the queries were executed or by descending duration.'),
      '#default_value' => $config->get('query_sort'),
    ];

    $form['database']['query_highlight'] = [
      '#type' => 'number',
      '#title' => $this->t('Slow query highlighting'),
      '#description' => $this->t('Enter an integer in milliseconds. Any query which takes longer than this many milliseconds will be highlighted in the query log. This indicates a possibly inefficient query, or a candidate for caching.'),
      '#default_value' => $config->get('query_highlight'),
      '#min' => 0,
    ];

    $storageId = $this->config('webprofiler.config')->get('storage');
    $storage = $this->storageManager->getStorage($storageId);

    $form['purge'] = [
      '#type' => 'details',
      '#title' => $this->t('Purge profiles'),
      '#open' => FALSE,
    ];

    $form['purge']['purge'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge'),
      '#submit' => [[$this, 'purge']],
    ];

    $form['purge']['purge-help'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="form-item">{{ message }}</div>',
      '#context' => [
        'message' => $this->t('Purge %storage profiles.', ['%storage' => $storage['title']]),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('webprofiler.config')
      ->set('purge_on_cache_clear', $form_state->getValue('purge_on_cache_clear'))
      ->set('storage', $form_state->getValue('storage'))
      ->set('exclude', $form_state->getValue('exclude'))
      ->set('active_toolbar_items', $form_state->getValue('active_toolbar_items'))
      ->set('ide_link', $form_state->getValue('ide_link'))
      ->set('ide_link_remote', $form_state->getValue('ide_link_remote'))
      ->set('ide_link_local', $form_state->getValue('ide_link_local'))
      ->set('query_sort', $form_state->getValue('query_sort'))
      ->set('query_highlight', $form_state->getValue('query_highlight'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Purges profiles.
   */
  public function purge(array &$form, FormStateInterface $form_state) {
    $this->profiler->purge();
    drupal_set_message($this->t('Profiles purged'));
  }

  /**
   * @return array
   */
  private function getCollectors() {
    $options = [];
    foreach ($this->templates as $template) {
      // drupal collector should not be disabled
      if ($template[0] != 'drupal') {
        $options[$template[0]] = $template[2];
      }
    }

    asort($options);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'webprofiler.config',
    ];
  }
}
