<?php

namespace Drupal\views_ui\Form\Ajax;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewEntityInterface;
use Drupal\views\Views;

/**
 * Provides a form for adding an item in the Views UI.
 *
 * @internal
 */
class AddHandler extends ViewsFormBase {

  /**
   * Constructs a new AddHandler object.
   */
  public function __construct($type = NULL) {
    $this->setType($type);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormKey() {
    return 'add-handler';
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(ViewEntityInterface $view, $display_id, $js, $type = NULL) {
    $this->setType($type);
    return parent::getForm($view, $display_id, $js);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_ui_add_handler_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $view = $form_state->get('view');
    $display_id = $form_state->get('display_id');
    $type = $form_state->get('type');

    $form = [
      'options' => [
        '#theme_wrappers' => ['container'],
        '#attributes' => ['class' => ['scroll'], 'data-drupal-views-scroll' => TRUE],
      ],
    ];

    $executable = $view->getExecutable();
    if (!$executable->setDisplay($display_id)) {
      $form['markup'] = ['#markup' => $this->t('Invalid display id @display', ['@display' => $display_id])];
      return $form;
    }
    $display = &$executable->displayHandlers->get($display_id);

    $types = ViewExecutable::getHandlerTypes();
    $ltitle = $types[$type]['ltitle'];
    $section = $types[$type]['plural'];

    if (!empty($types[$type]['type'])) {
      $type = $types[$type]['type'];
    }

    $form['#title'] = $this->t('Add @type', ['@type' => $ltitle]);
    $form['#section'] = $display_id . 'add-handler';

    // Add the display override dropdown.
    views_ui_standard_display_dropdown($form, $form_state, $section);

    // Figure out all the base tables allowed based upon what the relationships provide.
    $base_tables = $executable->getBaseTables();
    $options = Views::viewsDataHelper()->fetchFields(array_keys($base_tables), $type, $display->useGroupBy(), $form_state->get('type'));

    if (!empty($options)) {
      $form['override']['controls'] = [
        '#theme_wrappers' => ['container'],
        '#id' => 'views-filterable-options-controls',
        '#attributes' => ['class' => ['form--inline', 'views-filterable-options-controls']],
      ];
      $form['override']['controls']['options_search'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search'),
      ];

      $groups = ['all' => $this->t('- All -')];
      $form['override']['controls']['group'] = [
        '#type' => 'select',
        '#title' => $this->t('Category'),
        '#options' => [],
      ];

      $form['options']['name'] = [
        '#prefix' => '<div class="views-radio-box form-checkboxes views-filterable-options">',
        '#suffix' => '</div>',
        '#type' => 'tableselect',
        '#header' => [
          'title' => $this->t('Title'),
          'group' => $this->t('Category'),
          'help' => $this->t('Description'),
        ],
        '#js_select' => FALSE,
      ];

      $grouped_options = [];
      foreach ($options as $key => $option) {
        $group = preg_replace('/[^a-z0-9]/', '-', strtolower($option['group']));
        $groups[$group] = $option['group'];
        $grouped_options[$group][$key] = $option;
        if (!empty($option['aliases']) && is_array($option['aliases'])) {
          foreach ($option['aliases'] as $id => $alias) {
            if (empty($alias['base']) || !empty($base_tables[$alias['base']])) {
              $copy = $option;
              $copy['group'] = $alias['group'];
              $copy['title'] = $alias['title'];
              if (isset($alias['help'])) {
                $copy['help'] = $alias['help'];
              }

              $group = preg_replace('/[^a-z0-9]/', '-', strtolower($copy['group']));
              $groups[$group] = $copy['group'];
              $grouped_options[$group][$key . '$' . $id] = $copy;
            }
          }
        }
      }

      foreach ($grouped_options as $group => $group_options) {
        foreach ($group_options as $key => $option) {
          $form['options']['name']['#options'][$key] = [
            '#attributes' => [
              'class' => ['filterable-option', $group],
            ],
            'title' => [
              'data' => [
                '#title' => $option['title'],
                '#plain_text' => $option['title'],
              ],
              'class' => ['title'],
            ],
            'group' => $option['group'],
            'help' => [
              'data' => $option['help'],
              'class' => ['description'],
            ],
          ];
        }
      }

      $form['override']['controls']['group']['#options'] = $groups;
    }
    else {
      $form['options']['markup'] = [
        '#markup' => '<div class="js-form-item form-item">' . $this->t('There are no @types available to add.', ['@types' => $ltitle]) . '</div>',
      ];
    }
    // Add a div to show the selected items
    $form['selected'] = [
      '#type' => 'item',
      '#markup' => '<span class="views-ui-view-title">' . $this->t('Selected:') . '</span> ' . '<div class="views-selected-options"></div>',
      '#theme_wrappers' => ['form_element', 'views_ui_container'],
      '#attributes' => [
        'class' => ['container-inline', 'views-add-form-selected', 'views-offset-bottom'],
        'data-drupal-views-offset' => 'bottom',
      ],
    ];
    $view->getStandardButtons($form, $form_state, 'views_ui_add_handler_form', $this->t('Add and configure @types', ['@types' => $ltitle]));

    // Remove the default submit function.
    $form['actions']['submit']['#submit'] = array_filter($form['actions']['submit']['#submit'], function ($var) {
      return !(is_array($var) && isset($var[1]) && $var[1] == 'standardSubmit');
    });
    $form['actions']['submit']['#submit'][] = [$view, 'submitItemAdd'];

    return $form;
  }

}
