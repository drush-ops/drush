<?php

namespace Drupal\views\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\Plugin\views\wizard\WizardInterface;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ViewExecutable;

/**
 * @defgroup views_style_plugins Views style plugins
 * @{
 * Plugins that control how the collection of results is rendered in a view.
 *
 * Style plugins control how a view is displayed. For the most part, they are
 * object wrappers around theme templates. Examples of styles include HTML
 * lists, tables, full or teaser content views, etc.
 *
 * Many (but not all) style plugins have an optional row plugin, which
 * displays a single record. Not all style plugins use row plugins, so it is
 * up to the style plugin to set this up and call the row plugin. See the
 * @link views_row_plugins Views row plugins topic @endlink for more
 * information.
 *
 * Style plugins extend \Drupal\views\Plugin\views\style\StylePluginBase. They
 * must be annotated with \Drupal\views\Annotation\ViewsStyle
 * annotation, and they must be in namespace directory Plugin\views\style.
 *
 * @ingroup views_plugins
 * @see plugin_api
 */

/**
 * Base class for views style plugins.
 */
abstract class StylePluginBase extends PluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * Store all available tokens row rows.
   */
  protected $rowTokens = [];

  /**
   * Whether or not this style uses a row plugin.
   *
   * @var bool
   */
  protected $usesRowPlugin = FALSE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = FALSE;

  /**
   * Does the style plugin support grouping of rows.
   *
   * @var bool
   */
  protected $usesGrouping = TRUE;

  /**
   * Does the style plugin for itself support to add fields to its output.
   *
   * This option only makes sense on style plugins without row plugins, like
   * for example table.
   *
   * @var bool
   */
  protected $usesFields = FALSE;

  /**
   * Stores the rendered field values, keyed by the row index and field name.
   *
   * @see \Drupal\views\Plugin\views\style\StylePluginBase::renderFields()
   * @see \Drupal\views\Plugin\views\style\StylePluginBase::getField()
   *
   * @var array|null
   */
  protected $rendered_fields;

  /**
   * The theme function used to render the grouping set.
   *
   * Plugins may override this attribute if they wish to use some other theme
   * function to render the grouping set.
   *
   * @var string
   *
   * @see StylePluginBase::renderGroupingSets()
   */
  protected $groupingTheme = 'views_view_grouping';

  /**
   * Should field labels be enabled by default.
   *
   * @var bool
   */
  protected $defaultFieldLabels = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\PluginBase::init().
   *
   * The style options might come externally as the style can be sourced from at
   * least two locations. If it's not included, look on the display.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    if ($this->usesRowPlugin() && $display->getOption('row')) {
      $this->view->rowPlugin = $display->getPlugin('row');
    }

    $this->options += [
      'grouping' => [],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function destroy() {
    parent::destroy();

    if (isset($this->view->rowPlugin)) {
      $this->view->rowPlugin->destroy();
    }
  }

  /**
   * Returns the usesRowPlugin property.
   *
   * @return bool
   */
  public function usesRowPlugin() {
    return $this->usesRowPlugin;

  }

  /**
   * Returns the usesRowClass property.
   *
   * @return bool
   */
  public function usesRowClass() {
    return $this->usesRowClass;
  }

  /**
   * Returns the usesGrouping property.
   *
   * @return bool
   */
  public function usesGrouping() {
    return $this->usesGrouping;
  }

  /**
   * Return TRUE if this style also uses fields.
   *
   * @return bool
   */
  public function usesFields() {
    // If we use a row plugin, ask the row plugin. Chances are, we don't
    // care, it does.
    $row_uses_fields = FALSE;
    if ($this->usesRowPlugin() && ($row_plugin = $this->displayHandler->getPlugin('row'))) {
      $row_uses_fields = $row_plugin->usesFields();
    }
    // Otherwise, check the definition or the option.
    return $row_uses_fields || $this->usesFields || !empty($this->options['uses_fields']);
  }

  /**
   * Return TRUE if this style uses tokens.
   *
   * Used to ensure we don't fetch tokens when not needed for performance.
   */
  public function usesTokens() {
    if ($this->usesRowClass()) {
      $class = $this->options['row_class'];
      if (strpos($class, '{{') !== FALSE) {
        return TRUE;
      }
    }
  }

  /**
   * Return TRUE if this style enables field labels by default.
   *
   * @return bool
   */
  public function defaultFieldLabels() {
    return $this->defaultFieldLabels;
  }

  /**
   * Return the token replaced row class for the specified row.
   */
  public function getRowClass($row_index) {
    if ($this->usesRowClass()) {
      $class = $this->options['row_class'];
      if ($this->usesFields() && $this->view->field) {
        $class = strip_tags($this->tokenizeValue($class, $row_index));
      }

      $classes = explode(' ', $class);
      foreach ($classes as &$class) {
        $class = Html::cleanCssIdentifier($class);
      }
      return implode(' ', $classes);
    }
  }

  /**
   * Take a value and apply token replacement logic to it.
   */
  public function tokenizeValue($value, $row_index) {
    if (strpos($value, '{{') !== FALSE) {
      // Row tokens might be empty, for example for node row style.
      $tokens = isset($this->rowTokens[$row_index]) ? $this->rowTokens[$row_index] : [];
      if (!empty($this->view->build_info['substitutions'])) {
        $tokens += $this->view->build_info['substitutions'];
      }

      $value = $this->viewsTokenReplace($value, $tokens);
    }
    else {
      // ::viewsTokenReplace() will run Xss::filterAdmin on the
      // resulting string. We do the same here for consistency.
      $value = Xss::filterAdmin($value);
    }
    return $value;
  }

  /**
   * Should the output of the style plugin be rendered even if it's a empty view.
   */
  public function evenEmpty() {
    return !empty($this->definition['even empty']);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['grouping'] = ['default' => []];
    if ($this->usesRowClass()) {
      $options['row_class'] = ['default' => ''];
      $options['default_row_class'] = ['default' => TRUE];
    }
    $options['uses_fields'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    // Only fields-based views can handle grouping.  Style plugins can also exclude
    // themselves from being groupable by setting their "usesGrouping" property
    // to FALSE.
    // @TODO: Document "usesGrouping" in docs.php when docs.php is written.
    if ($this->usesFields() && $this->usesGrouping()) {
      $options = ['' => $this->t('- None -')];
      $field_labels = $this->displayHandler->getFieldLabels(TRUE);
      $options += $field_labels;
      // If there are no fields, we can't group on them.
      if (count($options) > 1) {
        // This is for backward compatibility, when there was just a single
        // select form.
        if (is_string($this->options['grouping'])) {
          $grouping = $this->options['grouping'];
          $this->options['grouping'] = [];
          $this->options['grouping'][0]['field'] = $grouping;
        }
        if (isset($this->options['group_rendered']) && is_string($this->options['group_rendered'])) {
          $this->options['grouping'][0]['rendered'] = $this->options['group_rendered'];
          unset($this->options['group_rendered']);
        }

        $c = count($this->options['grouping']);
        // Add a form for every grouping, plus one.
        for ($i = 0; $i <= $c; $i++) {
          $grouping = !empty($this->options['grouping'][$i]) ? $this->options['grouping'][$i] : [];
          $grouping += ['field' => '', 'rendered' => TRUE, 'rendered_strip' => FALSE];
          $form['grouping'][$i]['field'] = [
            '#type' => 'select',
            '#title' => $this->t('Grouping field Nr.@number', ['@number' => $i + 1]),
            '#options' => $options,
            '#default_value' => $grouping['field'],
            '#description' => $this->t('You may optionally specify a field by which to group the records. Leave blank to not group.'),
          ];
          $form['grouping'][$i]['rendered'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Use rendered output to group rows'),
            '#default_value' => $grouping['rendered'],
            '#description' => $this->t('If enabled the rendered output of the grouping field is used to group the rows.'),
            '#states' => [
              'invisible' => [
                ':input[name="style_options[grouping][' . $i . '][field]"]' => ['value' => ''],
              ],
            ],
          ];
          $form['grouping'][$i]['rendered_strip'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Remove tags from rendered output'),
            '#default_value' => $grouping['rendered_strip'],
            '#states' => [
              'invisible' => [
                ':input[name="style_options[grouping][' . $i . '][field]"]' => ['value' => ''],
              ],
            ],
          ];
        }
      }
    }

    if ($this->usesRowClass()) {
      $form['row_class'] = [
        '#title' => $this->t('Row class'),
        '#description' => $this->t('The class to provide on each row.'),
        '#type' => 'textfield',
        '#default_value' => $this->options['row_class'],
      ];

      if ($this->usesFields()) {
        $form['row_class']['#description'] .= ' ' . $this->t('You may use field tokens from as per the "Replacement patterns" used in "Rewrite the output of this field" for all fields.');
      }

      $form['default_row_class'] = [
        '#title' => $this->t('Add views row classes'),
        '#description' => $this->t('Add the default row classes like @classes to the output. You can use this to quickly reduce the amount of markup the view provides by default, at the cost of making it more difficult to apply CSS.', ['@classes' => 'views-row']),
        '#type' => 'checkbox',
        '#default_value' => $this->options['default_row_class'],
      ];
    }

    if (!$this->usesFields() || !empty($this->options['uses_fields'])) {
      $form['uses_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Force using fields'),
        '#description' => $this->t('If neither the row nor the style plugin supports fields, this field allows to enable them, so you can for example use groupby.'),
        '#default_value' => $this->options['uses_fields'],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    // Don't run validation on style plugins without the grouping setting.
    if ($form_state->hasValue(['style_options', 'grouping'])) {
      // Don't save grouping if no field is specified.
      $groupings = $form_state->getValue(['style_options', 'grouping']);
      foreach ($groupings as $index => $grouping) {
        if (empty($grouping['field'])) {
          $form_state->unsetValue(['style_options', 'grouping', $index]);
        }
      }
    }
  }

  /**
   * Provide a form in the views wizard if this style is selected.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $type
   *   The display type, either block or page.
   */
  public function wizardForm(&$form, FormStateInterface $form_state, $type) {
  }

  /**
   * Alter the options of a display before they are added to the view.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\views\Plugin\views\wizard\WizardInterface $wizard
   *   The current used wizard.
   * @param array $display_options
   *   The options which will be used on the view. The style plugin should
   *   alter this to its own needs.
   * @param string $display_type
   *   The display type, either block or page.
   */
  public function wizardSubmit(&$form, FormStateInterface $form_state, WizardInterface $wizard, &$display_options, $display_type) {
  }

  /**
   * Called by the view builder to see if this style handler wants to
   * interfere with the sorts. If so it should build; if it returns
   * any non-TRUE value, normal sorting will NOT be added to the query.
   */
  public function buildSort() {
    return TRUE;
  }

  /**
   * Called by the view builder to let the style build a second set of
   * sorts that will come after any other sorts in the view.
   */
  public function buildSortPost() {}

  /**
   * Allow the style to do stuff before each row is rendered.
   *
   * @param $result
   *   The full array of results from the query.
   */
  public function preRender($result) {
    if (!empty($this->view->rowPlugin)) {
      $this->view->rowPlugin->preRender($result);
    }
  }

  /**
   * Renders a group of rows of the grouped view.
   *
   * @param array $rows
   *   The result rows rendered in this group.
   *
   * @return array
   *   The render array containing the single group theme output.
   */
  protected function renderRowGroup(array $rows = []) {
    return [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#rows' => $rows,
    ];
  }

  /**
   * Render the display in this style.
   */
  public function render() {
    if ($this->usesRowPlugin() && empty($this->view->rowPlugin)) {
      trigger_error('Drupal\views\Plugin\views\style\StylePluginBase: Missing row plugin', E_WARNING);
      return [];
    }

    // Group the rows according to the grouping instructions, if specified.
    $sets = $this->renderGrouping(
      $this->view->result,
      $this->options['grouping'],
      TRUE
    );

    return $this->renderGroupingSets($sets);
  }

  /**
   * Render the grouping sets.
   *
   * Plugins may override this method if they wish some other way of handling
   * grouping.
   *
   * @param $sets
   *   An array keyed by group content containing the grouping sets to render.
   *   Each set contains the following associative array:
   *   - group: The group content.
   *   - level: The hierarchical level of the grouping.
   *   - rows: The result rows to be rendered in this group..
   *
   * @return array
   *   Render array of grouping sets.
   */
  public function renderGroupingSets($sets) {
    $output = [];
    $theme_functions = $this->view->buildThemeFunctions($this->groupingTheme);
    foreach ($sets as $set) {
      $level = isset($set['level']) ? $set['level'] : 0;

      $row = reset($set['rows']);
      // Render as a grouping set.
      if (is_array($row) && isset($row['group'])) {
        $single_output = [
          '#theme' => $theme_functions,
          '#view' => $this->view,
          '#grouping' => $this->options['grouping'][$level],
          '#rows' => $set['rows'],
        ];
      }
      // Render as a record set.
      else {
        if ($this->usesRowPlugin()) {
          foreach ($set['rows'] as $index => $row) {
            $this->view->row_index = $index;
            $set['rows'][$index] = $this->view->rowPlugin->render($row);
          }
        }

        $single_output = $this->renderRowGroup($set['rows']);
      }

      $single_output['#grouping_level'] = $level;
      $single_output['#title'] = $set['group'];
      $output[] = $single_output;
    }
    unset($this->view->row_index);
    return $output;
  }

  /**
   * Group records as needed for rendering.
   *
   * @param $records
   *   An array of records from the view to group.
   * @param $groupings
   *   An array of grouping instructions on which fields to group. If empty, the
   *   result set will be given a single group with an empty string as a label.
   * @param $group_rendered
   *   Boolean value whether to use the rendered or the raw field value for
   *   grouping. If set to NULL the return is structured as before
   *   Views 7.x-3.0-rc2. After Views 7.x-3.0 this boolean is only used if
   *   $groupings is an old-style string or if the rendered option is missing
   *   for a grouping instruction.
   * @return
   *   The grouped record set.
   *   A nested set structure is generated if multiple grouping fields are used.
   *
   *   @code
   *   array(
   *     'grouping_field_1:grouping_1' => array(
   *       'group' => 'grouping_field_1:content_1',
   *       'level' => 0,
   *       'rows' => array(
   *         'grouping_field_2:grouping_a' => array(
   *           'group' => 'grouping_field_2:content_a',
   *           'level' => 1,
   *           'rows' => array(
   *             $row_index_1 => $row_1,
   *             $row_index_2 => $row_2,
   *             // ...
   *           )
   *         ),
   *       ),
   *     ),
   *     'grouping_field_1:grouping_2' => array(
   *       // ...
   *     ),
   *   )
   *   @endcode
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {
    // This is for backward compatibility, when $groupings was a string
    // containing the ID of a single field.
    if (is_string($groupings)) {
      $rendered = $group_rendered === NULL ? TRUE : $group_rendered;
      $groupings = [['field' => $groupings, 'rendered' => $rendered]];
    }

    // Make sure fields are rendered
    $this->renderFields($this->view->result);
    $sets = [];
    if ($groupings) {
      foreach ($records as $index => $row) {
        // Iterate through configured grouping fields to determine the
        // hierarchically positioned set where the current row belongs to.
        // While iterating, parent groups, that do not exist yet, are added.
        $set = &$sets;
        foreach ($groupings as $level => $info) {
          $field = $info['field'];
          $rendered = isset($info['rendered']) ? $info['rendered'] : $group_rendered;
          $rendered_strip = isset($info['rendered_strip']) ? $info['rendered_strip'] : FALSE;
          $grouping = '';
          $group_content = '';
          // Group on the rendered version of the field, not the raw.  That way,
          // we can control any special formatting of the grouping field through
          // the admin or theme layer or anywhere else we'd like.
          if (isset($this->view->field[$field])) {
            $group_content = $this->getField($index, $field);
            if ($this->view->field[$field]->options['label']) {
              $group_content = $this->view->field[$field]->options['label'] . ': ' . $group_content;
            }
            if ($rendered) {
              $grouping = (string) $group_content;
              if ($rendered_strip) {
                $group_content = $grouping = strip_tags(htmlspecialchars_decode($group_content));
              }
            }
            else {
              $grouping = $this->getFieldValue($index, $field);
              // Not all field handlers return a scalar value,
              // e.g. views_handler_field_field.
              if (!is_scalar($grouping)) {
                $grouping = hash('sha256', serialize($grouping));
              }
            }
          }

          // Create the group if it does not exist yet.
          if (empty($set[$grouping])) {
            $set[$grouping]['group'] = $group_content;
            $set[$grouping]['level'] = $level;
            $set[$grouping]['rows'] = [];
          }

          // Move the set reference into the row set of the group we just determined.
          $set = &$set[$grouping]['rows'];
        }
        // Add the row to the hierarchically positioned row set we just determined.
        $set[$index] = $row;
      }
    }
    else {
      // Create a single group with an empty grouping field.
      $sets[''] = [
        'group' => '',
        'rows' => $records,
      ];
    }

    // If this parameter isn't explicitly set, modify the output to be fully
    // backward compatible to code before Views 7.x-3.0-rc2.
    // @TODO Remove this as soon as possible e.g. October 2020
    if ($group_rendered === NULL) {
      $old_style_sets = [];
      foreach ($sets as $group) {
        $old_style_sets[$group['group']] = $group['rows'];
      }
      $sets = $old_style_sets;
    }

    return $sets;
  }

  /**
   * Renders all of the fields for a given style and store them on the object.
   *
   * @param array $result
   *   The result array from $view->result
   */
  protected function renderFields(array $result) {
    if (!$this->usesFields()) {
      return;
    }

    if (!isset($this->rendered_fields)) {
      $this->rendered_fields = [];
      $this->view->row_index = 0;
      $field_ids = array_keys($this->view->field);

      // Only tokens relating to field handlers preceding the one we invoke
      // ::getRenderTokens() on are returned, so here we need to pick the last
      // available field handler.
      $render_tokens_field_id = end($field_ids);

      // If all fields have a field::access FALSE there might be no fields, so
      // there is no reason to execute this code.
      if (!empty($field_ids)) {
        $renderer = $this->getRenderer();
        /** @var \Drupal\views\Plugin\views\cache\CachePluginBase $cache_plugin */
        $cache_plugin = $this->view->display_handler->getPlugin('cache');
        $max_age = $cache_plugin->getCacheMaxAge();

        /** @var \Drupal\views\ResultRow $row */
        foreach ($result as $index => $row) {
          $this->view->row_index = $index;

          // Here we implement render caching for result rows. Since we never
          // build a render array for single rows, given that style templates
          // need individual field markup to support proper theming, we build
          // a raw render array containing all field render arrays and cache it.
          // This allows us to cache the markup of the various children, that is
          // individual fields, which is then available for style template
          // preprocess functions, later in the rendering workflow.
          // @todo Fetch all the available cached row items in one single cache
          //   get operation, once https://www.drupal.org/node/2453945 is fixed.
          $data = [
            '#pre_render' => [[$this, 'elementPreRenderRow']],
            '#row' => $row,
            '#cache' => [
              'keys' => $cache_plugin->getRowCacheKeys($row),
              'tags' => $cache_plugin->getRowCacheTags($row),
              'max-age' => $max_age,
            ],
            '#cache_properties' => $field_ids,
          ];
          $renderer->addCacheableDependency($data, $this->view->storage);
          // Views may be rendered both inside and outside a render context:
          // - HTML views are rendered inside a render context: then we want to
          //   use ::render(), so that attachments and cacheability are bubbled.
          // - non-HTML views are rendered outside a render context: then we
          //   want to use ::renderPlain(), so that no bubbling happens
          if ($renderer->hasRenderContext()) {
            $renderer->render($data);
          }
          else {
            $renderer->renderPlain($data);
          }

          // Extract field output from the render array and post process it.
          $fields = $this->view->field;
          $rendered_fields = &$this->rendered_fields[$index];
          $post_render_tokens = [];
          foreach ($field_ids as $id) {
            $rendered_fields[$id] = $data[$id]['#markup'];
            $tokens = $fields[$id]->postRender($row, $rendered_fields[$id]);
            if ($tokens) {
              $post_render_tokens += $tokens;
            }
          }

          // Populate row tokens.
          $this->rowTokens[$index] = $this->view->field[$render_tokens_field_id]->getRenderTokens([]);

          // Replace post-render tokens.
          if ($post_render_tokens) {
            $placeholders = array_keys($post_render_tokens);
            $values = array_values($post_render_tokens);
            foreach ($this->rendered_fields[$index] as &$rendered_field) {
              // Placeholders and rendered fields have been processed by the
              // render system and are therefore safe.
              $rendered_field = ViewsRenderPipelineMarkup::create(str_replace($placeholders, $values, $rendered_field));
            }
          }
        }
      }

      unset($this->view->row_index);
    }
  }

  /**
   * #pre_render callback for view row field rendering.
   *
   * @see self::render()
   *
   * @param array $data
   *   The element to #pre_render
   *
   * @return array
   *   The processed element.
   */
  public function elementPreRenderRow(array $data) {
    // Render row fields.
    foreach ($this->view->field as $id => $field) {
      $data[$id] = ['#markup' => $field->theme($data['#row'])];
    }
    return $data;
  }

  /**
   * Gets a rendered field.
   *
   * @param int $index
   *   The index count of the row.
   * @param string $field
   *   The ID of the field.
   *
   * @return \Drupal\Component\Render\MarkupInterface|null
   *   The output of the field, or NULL if it was empty.
   */
  public function getField($index, $field) {
    if (!isset($this->rendered_fields)) {
      $this->renderFields($this->view->result);
    }

    if (isset($this->rendered_fields[$index][$field])) {
      return $this->rendered_fields[$index][$field];
    }
  }

  /**
   * Get the raw field value.
   *
   * @param $index
   *   The index count of the row.
   * @param $field
   *   The id of the field.
   */
  public function getFieldValue($index, $field) {
    $this->view->row_index = $index;
    $value = $this->view->field[$field]->getValue($this->view->result[$index]);
    unset($this->view->row_index);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();

    if ($this->usesRowPlugin()) {
      $plugin = $this->displayHandler->getPlugin('row');
      if (empty($plugin)) {
        $errors[] = $this->t('Style @style requires a row style but the row plugin is invalid.', ['@style' => $this->definition['title']]);
      }
      else {
        $result = $plugin->validate();
        if (!empty($result) && is_array($result)) {
          $errors = array_merge($errors, $result);
        }
      }
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();
    if (isset($this->view->rowPlugin)) {
      $this->view->rowPlugin->query();
    }
  }

}

/**
 * @}
 */
