<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Entity\Render\EntityFieldRenderer;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\DependentWithRemovalPluginInterface;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A field that displays entity field data.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("field")
 */
class EntityField extends FieldPluginBase implements CacheableDependencyInterface, MultiItemsFieldHandlerInterface, DependentWithRemovalPluginInterface {

  use FieldAPIHandlerTrait;
  use PluginDependencyTrait;

  /**
   * An array to store field renderable arrays for use by renderItems().
   *
   * @var array
   */
  public $items = [];

  /**
   * Does the field supports multiple field values.
   *
   * @var bool
   */
  public $multiple;

  /**
   * Does the rendered fields get limited.
   *
   * @var bool
   */
  public $limit_values;

  /**
   * A shortcut for $view->base_table.
   *
   * @var string
   */
  public $base_table;

  /**
   * An array of formatter options.
   *
   * @var array
   */
  protected $formatterOptions;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The field formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $formatterPluginManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * Static cache for ::getEntityFieldRenderer().
   *
   * @var \Drupal\views\Entity\Render\EntityFieldRenderer
   */
  protected $entityFieldRenderer;

  /**
   * Constructs a \Drupal\field\Plugin\views\field\Field object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The field formatter plugin manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatter_plugin_manager
   *   The field formatter plugin manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin_manager
   *   The field plugin type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, FormatterPluginManager $formatter_plugin_manager, FieldTypePluginManagerInterface $field_type_plugin_manager, LanguageManagerInterface $language_manager, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->formatterPluginManager = $formatter_plugin_manager;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;

    // @todo Unify 'entity field'/'field_name' instead of converting back and
    //   forth. https://www.drupal.org/node/2410779
    if (isset($this->definition['entity field'])) {
      $this->definition['field_name'] = $this->definition['entity field'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('language_manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->multiple = FALSE;
    $this->limit_values = FALSE;

    $field_definition = $this->getFieldDefinition();
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    if ($field_definition->getFieldStorageDefinition()->isMultiple()) {
      $this->multiple = TRUE;

      // If "Display all values in the same row" is FALSE, then we always limit
      // in order to show a single unique value per row.
      if (!$this->options['group_rows']) {
        $this->limit_values = TRUE;
      }

      // If "First and last only" is chosen, limit the values
      if (!empty($this->options['delta_first_last'])) {
        $this->limit_values = TRUE;
      }

      // Otherwise, we only limit values if the user hasn't selected "all", 0, or
      // the value matching field cardinality.
      if ((($this->options['delta_limit'] > 0) && ($this->options['delta_limit'] != $cardinality)) || intval($this->options['delta_offset'])) {
        $this->limit_values = TRUE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $access_control_handler = $this->entityManager->getAccessControlHandler($this->getEntityType());
    return $access_control_handler->fieldAccess('view', $this->getFieldDefinition(), $account);
  }

  /**
   * Called to add the field to a query.
   *
   * By default, all needed data is taken from entities loaded by the query
   * plugin. Columns are added only if they are used in groupings.
   */
  public function query($use_groupby = FALSE) {
    $fields = $this->additional_fields;
    // No need to add the entity type.
    $entity_type_key = array_search('entity_type', $fields);
    if ($entity_type_key !== FALSE) {
      unset($fields[$entity_type_key]);
    }

    if ($use_groupby) {
      // Add the fields that we're actually grouping on.
      $options = [];
      if ($this->options['group_column'] != 'entity_id') {
        $options = [$this->options['group_column'] => $this->options['group_column']];
      }
      $options += is_array($this->options['group_columns']) ? $this->options['group_columns'] : [];

      // Go through the list and determine the actual column name from field api.
      $fields = [];
      $table_mapping = $this->getTableMapping();
      $field_definition = $this->getFieldStorageDefinition();

      foreach ($options as $column) {
        $fields[$column] = $table_mapping->getFieldColumnName($field_definition, $column);
      }

      $this->group_fields = $fields;
    }

    // Add additional fields (and the table join itself) if needed.
    if ($this->add_field_table($use_groupby)) {
      $this->ensureMyTable();
      $this->addAdditionalFields($fields);
    }

    // Let the entity field renderer alter the query if needed.
    $this->getEntityFieldRenderer()->query($this->query, $this->relationship);
  }

  /**
   * Determine if the field table should be added to the query.
   */
  public function add_field_table($use_groupby) {
    // Grouping is enabled.
    if ($use_groupby) {
      return TRUE;
    }
    // This a multiple value field, but "group multiple values" is not checked.
    if ($this->multiple && !$this->options['group_rows']) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    // A field is not click sortable if it's a multiple field with
    // "group multiple values" checked, since a click sort in that case would
    // add a join to the field table, which would produce unwanted duplicates.
    if ($this->multiple && $this->options['group_rows']) {
      return FALSE;
    }

    // If field definition is set, use that.
    if (isset($this->definition['click sortable'])) {
      return (bool) $this->definition['click sortable'];
    }

    // Default to true.
    return TRUE;
  }

  /**
   * Called to determine what to tell the clicksorter.
   */
  public function clickSort($order) {
    // No column selected, can't continue.
    if (empty($this->options['click_sort_column'])) {
      return;
    }

    $this->ensureMyTable();
    $field_storage_definition = $this->getFieldStorageDefinition();
    $column = $this->getTableMapping()->getFieldColumnName($field_storage_definition, $this->options['click_sort_column']);
    if (!isset($this->aliases[$column])) {
      // Column is not in query; add a sort on it (without adding the column).
      $this->aliases[$column] = $this->tableAlias . '.' . $column;
    }
    $this->query->addOrderBy(NULL, NULL, $order, $this->aliases[$column]);
  }

  /**
   * Gets the field storage definition.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
   *   The field storage definition used by this handler.
   */
  protected function getFieldStorageDefinition() {
    $entity_type_id = $this->definition['entity_type'];
    $field_storage_definitions = $this->entityManager->getFieldStorageDefinitions($entity_type_id);

    // @todo Unify 'entity field'/'field_name' instead of converting back and
    //   forth. https://www.drupal.org/node/2410779
    if (isset($this->definition['field_name']) && isset($field_storage_definitions[$this->definition['field_name']])) {
      return $field_storage_definitions[$this->definition['field_name']];
    }

    if (isset($this->definition['entity field']) && isset($field_storage_definitions[$this->definition['entity field']])) {
      return $field_storage_definitions[$this->definition['entity field']];
    }

    // The list of field storage definitions above does not include computed
    // base fields, so we need to explicitly fetch a list of all base fields in
    // order to support them.
    // @see \Drupal\Core\Entity\EntityFieldManager::getFieldStorageDefinitions()
    $base_fields = $this->entityManager->getBaseFieldDefinitions($entity_type_id);
    if (isset($this->definition['field_name']) && isset($base_fields[$this->definition['field_name']])) {
      return $base_fields[$this->definition['field_name']]->getFieldStorageDefinition();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $field_storage_definition = $this->getFieldStorageDefinition();
    $field_type = $this->fieldTypePluginManager->getDefinition($field_storage_definition->getType());
    $column_names = array_keys($field_storage_definition->getColumns());
    $default_column = '';
    // Try to determine a sensible default.
    if (count($column_names) == 1) {
      $default_column = $column_names[0];
    }
    elseif (in_array('value', $column_names)) {
      $default_column = 'value';
    }

    // If the field has a "value" column, we probably need that one.
    $options['click_sort_column'] = [
      'default' => $default_column,
    ];

    if (isset($this->definition['default_formatter'])) {
      $options['type'] = ['default' => $this->definition['default_formatter']];
    }
    elseif (isset($field_type['default_formatter'])) {
      $options['type'] = ['default' => $field_type['default_formatter']];
    }
    else {
      $options['type'] = ['default' => ''];
    }

    $options['settings'] = [
      'default' => isset($this->definition['default_formatter_settings']) ? $this->definition['default_formatter_settings'] : [],
    ];
    $options['group_column'] = [
      'default' => $default_column,
    ];
    $options['group_columns'] = [
      'default' => [],
    ];

    // Options used for multiple value fields.
    $options['group_rows'] = [
      'default' => TRUE,
    ];
    // If we know the exact number of allowed values, then that can be
    // the default. Otherwise, default to 'all'.
    $options['delta_limit'] = [
      'default' => ($field_storage_definition->getCardinality() > 1) ? $field_storage_definition->getCardinality() : 0,
    ];
    $options['delta_offset'] = [
      'default' => 0,
    ];
    $options['delta_reversed'] = [
      'default' => FALSE,
    ];
    $options['delta_first_last'] = [
      'default' => FALSE,
    ];

    $options['multi_type'] = [
      'default' => 'separator',
    ];
    $options['separator'] = [
      'default' => ', ',
    ];

    $options['field_api_classes'] = [
      'default' => FALSE,
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $field = $this->getFieldDefinition();
    $formatters = $this->formatterPluginManager->getOptions($field->getType());
    $column_names = array_keys($field->getColumns());

    // If this is a multiple value field, add its options.
    if ($this->multiple) {
      $this->multiple_options_form($form, $form_state);
    }

    // No need to ask the user anything if the field has only one column.
    if (count($field->getColumns()) == 1) {
      $form['click_sort_column'] = [
        '#type' => 'value',
        '#value' => isset($column_names[0]) ? $column_names[0] : '',
      ];
    }
    else {
      $form['click_sort_column'] = [
        '#type' => 'select',
        '#title' => $this->t('Column used for click sorting'),
        '#options' => array_combine($column_names, $column_names),
        '#default_value' => $this->options['click_sort_column'],
        '#description' => $this->t('Used by Style: Table to determine the actual column to click sort the field on. The default is usually fine.'),
      ];
    }

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatter'),
      '#options' => $formatters,
      '#default_value' => $this->options['type'],
      '#ajax' => [
        'url' => views_ui_build_form_url($form_state),
      ],
      '#submit' => [[$this, 'submitTemporaryForm']],
      '#executes_submit_callback' => TRUE,
    ];

    $form['field_api_classes'] = [
      '#title' => $this->t('Use field template'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['field_api_classes'],
      '#description' => $this->t('If checked, field api classes will be added by field templates. This is not recommended unless your CSS depends upon these classes. If not checked, template will not be used.'),
      '#fieldset' => 'style_settings',
      '#weight' => 20,
    ];

    if ($this->multiple) {
      $form['field_api_classes']['#description'] .= ' ' . $this->t('Checking this option will cause the group Display Type and Separator values to be ignored.');
    }

    // Get the settings form.
    $settings_form = ['#value' => []];
    $format = isset($form_state->getUserInput()['options']['type']) ? $form_state->getUserInput()['options']['type'] : $this->options['type'];
    if ($formatter = $this->getFormatterInstance($format)) {
      $settings_form = $formatter->settingsForm($form, $form_state);
      // Convert field UI selector states to work in the Views field form.
      FormHelper::rewriteStatesSelector($settings_form, "fields[{$field->getName()}][settings_edit_form]", 'options');
    }
    $form['settings'] = $settings_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormCalculateOptions(array $options, array $form_state_options) {
    // When we change the formatter type we don't want to keep any of the
    // previous configured formatter settings, as there might be schema
    // conflict.
    unset($options['settings']);
    $options = $form_state_options + $options;
    if (!isset($options['settings'])) {
      $options['settings'] = [];
    }
    return $options;
  }

  /**
   * Provide options for multiple value fields.
   */
  public function multiple_options_form(&$form, FormStateInterface $form_state) {
    $field = $this->getFieldDefinition();

    $form['multiple_field_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Multiple field settings'),
      '#weight' => 5,
    ];

    $form['group_rows'] = [
      '#title' => $this->t('Display all values in the same row'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['group_rows'],
      '#description' => $this->t('If checked, multiple values for this field will be shown in the same row. If not checked, each value in this field will create a new row. If using group by, please make sure to group by "Entity ID" for this setting to have any effect.'),
      '#fieldset' => 'multiple_field_settings',
    ];

    // Make the string translatable by keeping it as a whole rather than
    // translating prefix and suffix separately.
    list($prefix, $suffix) = explode('@count', $this->t('Display @count value(s)'));

    if ($field->getCardinality() == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $type = 'textfield';
      $options = NULL;
      $size = 5;
    }
    else {
      $type = 'select';
      $range = range(1, $field->getCardinality());
      $options = array_combine($range, $range);
      $size = 1;
    }
    $form['multi_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display type'),
      '#options' => [
        'ul' => $this->t('Unordered list'),
        'ol' => $this->t('Ordered list'),
        'separator' => $this->t('Simple separator'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#default_value' => $this->options['multi_type'],
      '#fieldset' => 'multiple_field_settings',
    ];

    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#default_value' => $this->options['separator'],
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
          ':input[name="options[multi_type]"]' => ['value' => 'separator'],
        ],
      ],
      '#fieldset' => 'multiple_field_settings',
    ];

    $form['delta_limit'] = [
      '#type' => $type,
      '#size' => $size,
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#options' => $options,
      '#default_value' => $this->options['delta_limit'],
      '#prefix' => '<div class="container-inline">',
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#fieldset' => 'multiple_field_settings',
    ];

    list($prefix, $suffix) = explode('@count', $this->t('starting from @count'));
    $form['delta_offset'] = [
      '#type' => 'textfield',
      '#size' => 5,
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $this->options['delta_offset'],
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('(first item is 0)'),
      '#fieldset' => 'multiple_field_settings',
    ];
    $form['delta_reversed'] = [
      '#title' => $this->t('Reversed'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['delta_reversed'],
      '#suffix' => $suffix,
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('(start from last values)'),
      '#fieldset' => 'multiple_field_settings',
    ];
    $form['delta_first_last'] = [
      '#title' => $this->t('First and last only'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['delta_first_last'],
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="options[group_rows]"]' => ['checked' => TRUE],
        ],
      ],
      '#fieldset' => 'multiple_field_settings',
    ];
  }

  /**
   * Extend the groupby form with group columns.
   */
  public function buildGroupByForm(&$form, FormStateInterface $form_state) {
    parent::buildGroupByForm($form, $form_state);
    // With "field API" fields, the column target of the grouping function
    // and any additional grouping columns must be specified.

    $field_columns = array_keys($this->getFieldDefinition()->getColumns());
    $group_columns = [
      'entity_id' => $this->t('Entity ID'),
    ] + array_map('ucfirst', array_combine($field_columns, $field_columns));

    $form['group_column'] = [
      '#type' => 'select',
      '#title' => $this->t('Group column'),
      '#default_value' => $this->options['group_column'],
      '#description' => $this->t('Select the column of this field to apply the grouping function selected above.'),
      '#options' => $group_columns,
    ];

    $options = [
      'bundle' => 'Bundle',
      'language' => 'Language',
      'entity_type' => 'Entity_type',
    ];
    // Add on defined fields, noting that they're prefixed with the field name.
    $form['group_columns'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Group columns (additional)'),
      '#default_value' => $this->options['group_columns'],
      '#description' => $this->t('Select any additional columns of this field to include in the query and to group on.'),
      '#options' => $options + $group_columns,
    ];
  }

  public function submitGroupByForm(&$form, FormStateInterface $form_state) {
    parent::submitGroupByForm($form, $form_state);
    $item = &$form_state->get('handler')->options;

    // Add settings for "field API" fields.
    $item['group_column'] = $form_state->getValue(['options', 'group_column']);
    $item['group_columns'] = array_filter($form_state->getValue(['options', 'group_columns']));
  }

  /**
   * Render all items in this field together.
   *
   * When using advanced render, each possible item in the list is rendered
   * individually. Then the items are all pasted together.
   */
  public function renderItems($items) {
    if (!empty($items)) {
      $items = $this->prepareItemsByDelta($items);
      if ($this->options['multi_type'] == 'separator' || !$this->options['group_rows']) {
        $separator = $this->options['multi_type'] == 'separator' ? Xss::filterAdmin($this->options['separator']) : '';
        $build = [
          '#type' => 'inline_template',
          '#template' => '{{ items | safe_join(separator) }}',
          '#context' => ['separator' => $separator, 'items' => $items],
        ];
      }
      else {
        $build = [
          '#theme' => 'item_list',
          '#items' => $items,
          '#title' => NULL,
          '#list_type' => $this->options['multi_type'],
        ];
      }
      return $this->renderer->render($build);
    }
  }

  /**
   * Adapts the $items according to the delta configuration.
   *
   * This selects displayed deltas, reorders items, and takes offsets into
   * account.
   *
   * @param array $all_values
   *   The items for individual rendering.
   *
   * @return array
   *   The manipulated items.
   */
  protected function prepareItemsByDelta(array $all_values) {
    if ($this->options['delta_reversed']) {
      $all_values = array_reverse($all_values);
    }

    // We are supposed to show only certain deltas.
    if ($this->limit_values) {
      $row = $this->view->result[$this->view->row_index];

      // Offset is calculated differently when row grouping for a field is not
      // enabled. Since there are multiple rows, delta needs to be taken into
      // account, so that different values are shown per row.
      if (!$this->options['group_rows'] && isset($this->aliases['delta']) && isset($row->{$this->aliases['delta']})) {
        $delta_limit = 1;
        $offset = $row->{$this->aliases['delta']};
      }
      // Single fields don't have a delta available so choose 0.
      elseif (!$this->options['group_rows'] && !$this->multiple) {
        $delta_limit = 1;
        $offset = 0;
      }
      else {
        $delta_limit = $this->options['delta_limit'];
        $offset = intval($this->options['delta_offset']);

        // We should only get here in this case if there is an offset, and in
        // that case we are limiting to all values after the offset.
        if ($delta_limit === 0) {
          $delta_limit = count($all_values) - $offset;
        }
      }

      // Determine if only the first and last values should be shown.
      $delta_first_last = $this->options['delta_first_last'];

      $new_values = [];
      for ($i = 0; $i < $delta_limit; $i++) {
        $new_delta = $offset + $i;

        if (isset($all_values[$new_delta])) {
          // If first-last option was selected, only use the first and last
          // values.
          if (!$delta_first_last
            // Use the first value.
            || $new_delta == $offset
            // Use the last value.
            || $new_delta == ($delta_limit + $offset - 1)) {
            $new_values[] = $all_values[$new_delta];
          }
        }
      }
      $all_values = $new_values;
    }

    return $all_values;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);
    $this->getEntityFieldRenderer()->preRender($values);
  }

  /**
   * Returns the entity field renderer.
   *
   * @return \Drupal\views\Entity\Render\EntityFieldRenderer
   *   The entity field renderer.
   */
  protected function getEntityFieldRenderer() {
    if (!isset($this->entityFieldRenderer)) {
      // This can be invoked during field handler initialization in which case
      // view fields are not set yet.
      if (!empty($this->view->field)) {
        foreach ($this->view->field as $field) {
          // An entity field renderer can handle only a single relationship.
          if ($field->relationship == $this->relationship && isset($field->entityFieldRenderer)) {
            $this->entityFieldRenderer = $field->entityFieldRenderer;
            break;
          }
        }
      }
      if (!isset($this->entityFieldRenderer)) {
        $entity_type = $this->entityManager->getDefinition($this->getEntityType());
        $this->entityFieldRenderer = new EntityFieldRenderer($this->view, $this->relationship, $this->languageManager, $entity_type, $this->entityManager);
      }
    }
    return $this->entityFieldRenderer;
  }

  /**
   * Gets an array of items for the field.
   *
   * @param \Drupal\views\ResultRow $values
   *   The result row object containing the values.
   *
   * @return array
   *   An array of items for the field.
   */
  public function getItems(ResultRow $values) {
    if (!$this->displayHandler->useGroupBy()) {
      $build_list = $this->getEntityFieldRenderer()->render($values, $this);
    }
    else {
      // For grouped results we need to retrieve a massaged entity having
      // grouped field values to ensure that "grouped by" values, especially
      // those with multiple cardinality work properly. See
      // \Drupal\Tests\views\Kernel\QueryGroupByTest::testGroupByFieldWithCardinality.
      $display = [
        'type' => $this->options['type'],
        'settings' => $this->options['settings'],
        'label' => 'hidden',
      ];
      // Optional relationships may not provide an entity at all. So we can't
      // use createEntityForGroupBy() for those rows.
      if ($entity = $this->getEntity($values)) {
        $entity = $this->createEntityForGroupBy($entity, $values);
        // Some bundles might not have a specific field, in which case the faked
        // entity doesn't have it either.
        $build_list = isset($entity->{$this->definition['field_name']}) ? $entity->{$this->definition['field_name']}->view($display) : NULL;
      }
      else {
        $build_list = NULL;
      }
    }

    if (!$build_list) {
      return [];
    }

    if ($this->options['field_api_classes']) {
      return [['rendered' => $this->renderer->render($build_list)]];
    }

    // Render using the formatted data itself.
    $items = [];
    // Each item is extracted and rendered separately, the top-level formatter
    // render array itself is never rendered, so we extract its bubbleable
    // metadata and add it to each child individually.
    $bubbleable = BubbleableMetadata::createFromRenderArray($build_list);
    foreach (Element::children($build_list) as $delta) {
      BubbleableMetadata::createFromRenderArray($build_list[$delta])
        ->merge($bubbleable)
        ->applyTo($build_list[$delta]);
      $items[$delta] = [
        'rendered' => $build_list[$delta],
        // Add the raw field items (for use in tokens).
        'raw' => $build_list['#items'][$delta],
      ];
    }
    return $items;
  }

  /**
   * Creates a fake entity with grouped field values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be processed.
   * @param \Drupal\views\ResultRow $row
   *   The result row object containing the values.
   *
   * @return bool|\Drupal\Core\Entity\FieldableEntityInterface
   *   Returns a new entity object containing the grouped field values.
   */
  protected function createEntityForGroupBy(EntityInterface $entity, ResultRow $row) {
    // Retrieve the correct translation object.
    $processed_entity = clone $this->getEntityFieldRenderer()->getEntityTranslation($entity, $row);

    // Copy our group fields into the cloned entity. It is possible this will
    // cause some weirdness, but there is only so much we can hope to do.
    if (!empty($this->group_fields) && isset($entity->{$this->definition['field_name']})) {
      // first, test to see if we have a base value.
      $base_value = [];
      // Note: We would copy original values here, but it can cause problems.
      // For example, text fields store cached filtered values as 'safe_value'
      // which does not appear anywhere in the field definition so we cannot
      // affect it. Other side effects could happen similarly.
      $data = FALSE;
      foreach ($this->group_fields as $field_name => $column) {
        if (property_exists($row, $this->aliases[$column])) {
          $base_value[$field_name] = $row->{$this->aliases[$column]};
          if (isset($base_value[$field_name])) {
            $data = TRUE;
          }
        }
      }

      // If any of our aggregated fields have data, fake it:
      if ($data) {
        // Now, overwrite the original value with our aggregated value.
        // This overwrites it so there is always just one entry.
        $processed_entity->{$this->definition['field_name']} = [$base_value];
      }
      else {
        $processed_entity->{$this->definition['field_name']} = [];
      }
    }

    return $processed_entity;
  }

  public function render_item($count, $item) {
    return render($item['rendered']);
  }

  protected function documentSelfTokens(&$tokens) {
    $field = $this->getFieldDefinition();
    foreach ($field->getColumns() as $id => $column) {
      $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = $this->t('Raw @column', ['@column' => $id]);
    }
  }

  protected function addSelfTokens(&$tokens, $item) {
    $field = $this->getFieldDefinition();
    foreach ($field->getColumns() as $id => $column) {
      // Use \Drupal\Component\Utility\Xss::filterAdmin() because it's user data
      // and we can't be sure it is safe. We know nothing about the data,
      // though, so we can't really do much else.
      if (isset($item['raw'])) {
        $raw = $item['raw'];

        if (is_array($raw)) {
          if (isset($raw[$id]) && is_scalar($raw[$id])) {
            $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = Xss::filterAdmin($raw[$id]);
          }
          else {
            // Make sure that empty values are replaced as well.
            $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = '';
          }
        }

        if (is_object($raw)) {
          $property = $raw->get($id);
          // Check if TypedDataInterface is implemented so we know how to render
          // the item as a string.
          if (!empty($property) && $property instanceof TypedDataInterface) {
            $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = Xss::filterAdmin($property->getString());
          }
          else {
            // Make sure that empty values are replaced as well.
            $tokens['{{ ' . $this->options['id'] . '__' . $id . ' }}'] = '';
          }
        }
      }
    }
  }

  /**
   * Returns the field formatter instance.
   *
   * @return \Drupal\Core\Field\FormatterInterface|null
   *   The field formatter instance.
   */
  protected function getFormatterInstance($format = NULL) {
    if (!isset($format)) {
      $format = $this->options['type'];
    }
    $settings = $this->options['settings'] + $this->formatterPluginManager->getDefaultSettings($format);

    $options = [
      'field_definition' => $this->getFieldDefinition(),
      'configuration' => [
        'type' => $format,
        'settings' => $settings,
        'label' => '',
        'weight' => 0,
      ],
      'view_mode' => '_custom',
    ];

    return $this->formatterPluginManager->getInstance($options);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies = parent::calculateDependencies();

    // Add the module providing the configured field storage as a dependency.
    if (($field_storage_definition = $this->getFieldStorageDefinition()) && $field_storage_definition instanceof EntityInterface) {
      $this->dependencies['config'][] = $field_storage_definition->getConfigDependencyName();
    }
    if (!empty($this->options['type'])) {
      // Add the module providing the formatter.
      $this->dependencies['module'][] = $this->formatterPluginManager->getDefinition($this->options['type'])['provider'];

      // Add the formatter's dependencies.
      if (($formatter = $this->getFormatterInstance()) && $formatter instanceof DependentPluginInterface) {
        $this->calculatePluginDependencies($formatter);
      }
    }

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getEntityFieldRenderer()->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $field_definition = $this->getFieldDefinition();
    $field_storage_definition = $this->getFieldStorageDefinition();
    return Cache::mergeTags(
      $field_definition instanceof CacheableDependencyInterface ? $field_definition->getCacheTags() : [],
      $field_storage_definition instanceof CacheableDependencyInterface ? $field_storage_definition->getCacheTags() : []
    );
  }

  /**
   * Gets the table mapping for the entity type of the field.
   *
   * @return \Drupal\Core\Entity\Sql\DefaultTableMapping
   *   The table mapping.
   */
  protected function getTableMapping() {
    return $this->entityManager->getStorage($this->definition['entity_type'])->getTableMapping();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $entity = $this->getEntity($values);
    // Retrieve the translated object.
    $translated_entity = $this->getEntityFieldRenderer()->getEntityTranslation($entity, $values);

    // Some bundles might not have a specific field, in which case the entity
    // (potentially a fake one) doesn't have it either.
    /** @var \Drupal\Core\Field\FieldItemListInterface $field_item_list */
    $field_item_list = isset($translated_entity->{$this->definition['field_name']}) ? $translated_entity->{$this->definition['field_name']} : NULL;

    if (!isset($field_item_list)) {
      // There isn't anything we can do without a valid field.
      return NULL;
    }

    $field_item_definition = $field_item_list->getFieldDefinition();

    $values = [];
    foreach ($field_item_list as $field_item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      if ($field) {
        $values[] = $field_item->$field;
      }
      // Find the value using the main property of the field. If no main
      // property is provided fall back to 'value'.
      elseif ($main_property_name = $field_item->mainPropertyName()) {
        $values[] = $field_item->{$main_property_name};
      }
      else {
        $values[] = $field_item->value;
      }
    }
    if ($field_item_definition->getFieldStorageDefinition()->getCardinality() == 1) {
      return reset($values);
    }
    else {
      return $values;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    // See if this handler is responsible for any of the dependencies being
    // removed. If this is the case, indicate that this handler needs to be
    // removed from the View.
    $remove = FALSE;
    // Get all the current dependencies for this handler.
    $current_dependencies = $this->calculateDependencies();
    foreach ($current_dependencies as $group => $dependency_list) {
      // Check if any of the handler dependencies match the dependencies being
      // removed.
      foreach ($dependency_list as $config_key) {
        if (isset($dependencies[$group]) && array_key_exists($config_key, $dependencies[$group])) {
          // This handlers dependency matches a dependency being removed,
          // indicate that this handler needs to be removed.
          $remove = TRUE;
          break 2;
        }
      }
    }
    return $remove;
  }

}
