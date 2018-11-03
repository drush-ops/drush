<?php

namespace Drupal\language\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSelected;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the selected language negotiation method for this site.
 *
 * @internal
 */
class NegotiationConfigureForm extends ConfigFormBase {

  /**
   * Stores the configuration object for language.types.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $languageTypes;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The language negotiator.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected $negotiator;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $blockStorage;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a NegotiationConfigureForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\language\LanguageNegotiatorInterface $negotiator
   *   The language negotiation methods manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Entity\EntityStorageInterface $block_storage
   *   The block storage, or NULL if not available.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ConfigurableLanguageManagerInterface $language_manager, LanguageNegotiatorInterface $negotiator, BlockManagerInterface $block_manager, ThemeHandlerInterface $theme_handler, EntityStorageInterface $block_storage = NULL) {
    parent::__construct($config_factory);
    $this->languageTypes = $this->config('language.types');
    $this->languageManager = $language_manager;
    $this->negotiator = $negotiator;
    $this->blockManager = $block_manager;
    $this->themeHandler = $theme_handler;
    $this->blockStorage = $block_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    $block_storage = $entity_manager->hasHandler('block', 'storage') ? $entity_manager->getStorage('block') : NULL;
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('language_negotiator'),
      $container->get('plugin.manager.block'),
      $container->get('theme_handler'),
      $block_storage
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'language_negotiation_configure_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['language.types'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $configurable = $this->languageTypes->get('configurable');

    $form = [
      '#theme' => 'language_negotiation_configure_form',
      '#language_types_info' => $this->languageManager->getDefinedLanguageTypesInfo(),
      '#language_negotiation_info' => $this->negotiator->getNegotiationMethods(),
    ];
    $form['#language_types'] = [];

    foreach ($form['#language_types_info'] as $type => $info) {
      // Show locked language types only if they are configurable.
      if (empty($info['locked']) || in_array($type, $configurable)) {
        $form['#language_types'][] = $type;
      }
    }

    foreach ($form['#language_types'] as $type) {
      $this->configureFormTable($form, $type);
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save settings'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $configurable_types = $form['#language_types'];

    $stored_values = $this->languageTypes->get('configurable');
    $customized = [];
    $method_weights_type = [];

    foreach ($configurable_types as $type) {
      $customized[$type] = in_array($type, $stored_values);
      $method_weights = [];
      $enabled_methods = $form_state->getValue([$type, 'enabled']);
      $enabled_methods[LanguageNegotiationSelected::METHOD_ID] = TRUE;
      $method_weights_input = $form_state->getValue([$type, 'weight']);
      if ($form_state->hasValue([$type, 'configurable'])) {
        $customized[$type] = !$form_state->isValueEmpty([$type, 'configurable']);
      }

      foreach ($method_weights_input as $method_id => $weight) {
        if ($enabled_methods[$method_id]) {
          $method_weights[$method_id] = $weight;
        }
      }

      $method_weights_type[$type] = $method_weights;
      $this->languageTypes->set('negotiation.' . $type . '.method_weights', $method_weights_input)->save();
    }

    // Update non-configurable language types and the related language
    // negotiation configuration.
    $this->negotiator->updateConfiguration(array_keys(array_filter($customized)));

    // Update the language negotiations after setting the configurability.
    foreach ($method_weights_type as $type => $method_weights) {
      $this->negotiator->saveConfiguration($type, $method_weights);
    }

    // Clear block definitions cache since the available blocks and their names
    // may have been changed based on the configurable types.
    if ($this->blockStorage) {
      // If there is an active language switcher for a language type that has
      // been made not configurable, deactivate it first.
      $non_configurable = array_keys(array_diff($customized, array_filter($customized)));
      $this->disableLanguageSwitcher($non_configurable);
    }
    $this->blockManager->clearCachedDefinitions();

    $form_state->setRedirect('language.negotiation');
    $this->messenger()->addStatus($this->t('Language detection configuration saved.'));
  }

  /**
   * Builds a language negotiation method configuration table.
   *
   * @param array $form
   *   The language negotiation configuration form.
   * @param string $type
   *   The language type to generate the table for.
   */
  protected function configureFormTable(array &$form, $type) {
    $info = $form['#language_types_info'][$type];

    $table_form = [
      '#title' => $this->t('@type language detection', ['@type' => $info['name']]),
      '#tree' => TRUE,
      '#description' => $info['description'],
      '#language_negotiation_info' => [],
      '#show_operations' => FALSE,
      'weight' => ['#tree' => TRUE],
    ];
    // Only show configurability checkbox for the unlocked language types.
    if (empty($info['locked'])) {
      $configurable = $this->languageTypes->get('configurable');
      $table_form['configurable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Customize %language_name language detection to differ from Interface text language detection settings', ['%language_name' => $info['name']]),
        '#default_value' => in_array($type, $configurable),
        '#attributes' => ['class' => ['language-customization-checkbox']],
        '#attached' => [
          'library' => [
            'language/drupal.language.admin',
          ],
        ],
      ];
    }

    $negotiation_info = $form['#language_negotiation_info'];
    $enabled_methods = $this->languageTypes->get('negotiation.' . $type . '.enabled') ?: [];
    $methods_weight = $this->languageTypes->get('negotiation.' . $type . '.method_weights') ?: [];

    // Add missing data to the methods lists.
    foreach ($negotiation_info as $method_id => $method) {
      if (!isset($methods_weight[$method_id])) {
        $methods_weight[$method_id] = isset($method['weight']) ? $method['weight'] : 0;
      }
    }

    // Order methods list by weight.
    asort($methods_weight);

    foreach ($methods_weight as $method_id => $weight) {
      // A language method might be no more available if the defining module has
      // been disabled after the last configuration saving.
      if (!isset($negotiation_info[$method_id])) {
        continue;
      }

      $enabled = isset($enabled_methods[$method_id]);
      $method = $negotiation_info[$method_id];

      // List the method only if the current type is defined in its 'types' key.
      // If it is not defined default to all the configurable language types.
      $types = array_flip(isset($method['types']) ? $method['types'] : $form['#language_types']);

      if (isset($types[$type])) {
        $table_form['#language_negotiation_info'][$method_id] = $method;
        $method_name = $method['name'];

        $table_form['weight'][$method_id] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title language detection method', ['@title' => mb_strtolower($method_name)]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#attributes' => ['class' => ["language-method-weight-$type"]],
          '#delta' => 20,
        ];

        $table_form['title'][$method_id] = ['#plain_text' => $method_name];

        $table_form['enabled'][$method_id] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable @title language detection method', ['@title' => mb_strtolower($method_name)]),
          '#title_display' => 'invisible',
          '#default_value' => $enabled,
        ];
        if ($method_id === LanguageNegotiationSelected::METHOD_ID) {
          $table_form['enabled'][$method_id]['#default_value'] = TRUE;
          $table_form['enabled'][$method_id]['#attributes'] = ['disabled' => 'disabled'];
        }

        $table_form['description'][$method_id] = ['#markup' => $method['description']];

        $config_op = [];
        if (isset($method['config_route_name'])) {
          $config_op['configure'] = [
            'title' => $this->t('Configure'),
            'url' => Url::fromRoute($method['config_route_name']),
          ];
          // If there is at least one operation enabled show the operation
          // column.
          $table_form['#show_operations'] = TRUE;
        }
        $table_form['operation'][$method_id] = [
         '#type' => 'operations',
         '#links' => $config_op,
        ];
      }
    }
    $form[$type] = $table_form;
  }

  /**
   * Disables the language switcher blocks.
   *
   * @param array $language_types
   *   An array containing all language types whose language switchers need to
   *   be disabled.
   */
  protected function disableLanguageSwitcher(array $language_types) {
    $theme = $this->themeHandler->getDefault();
    $blocks = $this->blockStorage->loadByProperties(['theme' => $theme]);
    foreach ($language_types as $language_type) {
      foreach ($blocks as $block) {
        if ($block->getPluginId() == 'language_block:' . $language_type) {
          $block->delete();
        }
      }
    }
  }

}
