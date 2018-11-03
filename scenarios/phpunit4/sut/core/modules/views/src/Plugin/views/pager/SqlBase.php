<?php

namespace Drupal\views\Plugin\views\pager;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * A common base class for sql based pager.
 */
abstract class SqlBase extends PagerPluginBase implements CacheableDependencyInterface {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['items_per_page'] = ['default' => 10];
    $options['offset'] = ['default' => 0];
    $options['id'] = ['default' => 0];
    $options['total_pages'] = ['default' => ''];
    $options['expose'] = [
      'contains' => [
        'items_per_page' => ['default' => FALSE],
        'items_per_page_label' => ['default' => $this->t('Items per page')],
        'items_per_page_options' => ['default' => '5, 10, 25, 50'],
        'items_per_page_options_all' => ['default' => FALSE],
        'items_per_page_options_all_label' => ['default' => $this->t('- All -')],

        'offset' => ['default' => FALSE],
        'offset_label' => ['default' => $this->t('Offset')],
      ],
    ];
    $options['tags'] = [
      'contains' => [
        'previous' => ['default' => $this->t('‹ Previous')],
        'next' => ['default' => $this->t('Next ›')],
      ],
    ];
    return $options;
  }

  /**
   * Provide the default form for setting options.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $pager_text = $this->displayHandler->getPagerText();
    $form['items_per_page'] = [
      '#title' => $pager_text['items per page title'],
      '#type' => 'number',
      '#description' => $pager_text['items per page description'],
      '#default_value' => $this->options['items_per_page'],
    ];

    $form['offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Offset (number of items to skip)'),
      '#description' => $this->t('For example, set this to 3 and the first 3 items will not be displayed.'),
      '#default_value' => $this->options['offset'],
    ];

    $form['id'] = [
      '#type' => 'number',
      '#title' => $this->t('Pager ID'),
      '#description' => $this->t("Unless you're experiencing problems with pagers related to this view, you should leave this at 0. If using multiple pagers on one page you may need to set this number to a higher value so as not to conflict within the ?page= array. Large values will add a lot of commas to your URLs, so avoid if possible."),
      '#default_value' => $this->options['id'],
    ];

    $form['total_pages'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of pages'),
      '#description' => $this->t('Leave empty to show all pages.'),
      '#default_value' => $this->options['total_pages'],
    ];

    $form['tags'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => $this->t('Pager link labels'),
      '#input' => TRUE,
    ];

    $form['tags']['previous'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Previous page link text'),
      '#default_value' => $this->options['tags']['previous'],
    ];

    $form['tags']['next'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Next page link text'),
      '#default_value' => $this->options['tags']['next'],
    ];

    $form['expose'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#title' => $this->t('Exposed options'),
      '#input' => TRUE,
      '#description' => $this->t('Allow user to control selected display options for this view.'),
    ];

    $form['expose']['items_per_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow user to control the number of items displayed in this view'),
      '#default_value' => $this->options['expose']['items_per_page'],
    ];

    $form['expose']['items_per_page_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Items per page label'),
      '#required' => TRUE,
      '#default_value' => $this->options['expose']['items_per_page_label'],
      '#states' => [
        'invisible' => [
          'input[name="pager_options[expose][items_per_page]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['expose']['items_per_page_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exposed items per page options'),
      '#required' => TRUE,
      '#description' => $this->t('Set between which values the user can choose when determining the items per page. Separated by comma.'),
      '#default_value' => $this->options['expose']['items_per_page_options'],
      '#states' => [
        'invisible' => [
          'input[name="pager_options[expose][items_per_page]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['expose']['items_per_page_options_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow user to display all items'),
      '#default_value' => $this->options['expose']['items_per_page_options_all'],
    ];

    $form['expose']['items_per_page_options_all_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('All items label'),
      '#default_value' => $this->options['expose']['items_per_page_options_all_label'],
      '#states' => [
        'invisible' => [
          'input[name="pager_options[expose][items_per_page_options_all]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['expose']['offset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow user to specify number of items skipped from beginning of this view.'),
      '#default_value' => $this->options['expose']['offset'],
    ];

    $form['expose']['offset_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Offset label'),
      '#required' => TRUE,
      '#default_value' => $this->options['expose']['offset_label'],
      '#states' => [
        'invisible' => [
          'input[name="pager_options[expose][offset]"]' => ['checked' => FALSE],
        ],
      ],
    ];
  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    // Only accept integer values.
    $error = FALSE;
    $exposed_options = $form_state->getValue(['pager_options', 'expose', 'items_per_page_options']);
    if (strpos($exposed_options, '.') !== FALSE) {
      $error = TRUE;
    }
    $options = explode(',', $exposed_options);
    if (!$error && is_array($options)) {
      foreach ($options as $option) {
        if (!is_numeric($option) || intval($option) == 0) {
          $error = TRUE;
        }
      }
    }
    else {
      $error = TRUE;
    }
    if ($error) {
      $form_state->setErrorByName('pager_options][expose][items_per_page_options', $this->t('Insert a list of integer numeric values separated by commas: e.g: 10, 20, 50, 100'));
    }

    // Make sure that the items_per_page is part of the expose settings.
    if (!$form_state->isValueEmpty(['pager_options', 'expose', 'items_per_page']) && !$form_state->isValueEmpty(['pager_options', 'items_per_page'])) {
      $items_per_page = $form_state->getValue(['pager_options', 'items_per_page']);
      if (array_search($items_per_page, $options) === FALSE) {
        $form_state->setErrorByName('pager_options][expose][items_per_page_options', $this->t("The <em>Exposed items per page</em> field's options must include the value from the <em>Items per page</em> field (@items_per_page).",
          ['@items_per_page' => $items_per_page])
        );
      }
    }
  }

  public function query() {
    if ($this->itemsPerPageExposed()) {
      $query = $this->view->getRequest()->query;
      $items_per_page = $query->get('items_per_page');
      if ($items_per_page > 0) {
        $this->options['items_per_page'] = $items_per_page;
      }
      elseif ($items_per_page == 'All' && $this->options['expose']['items_per_page_options_all']) {
        $this->options['items_per_page'] = 0;
      }
    }
    if ($this->isOffsetExposed()) {
      $query = $this->view->getRequest()->query;
      $offset = $query->get('offset');
      if (isset($offset) && $offset >= 0) {
        $this->options['offset'] = $offset;
      }
    }

    $limit = $this->options['items_per_page'];
    $offset = $this->current_page * $this->options['items_per_page'] + $this->options['offset'];
    if (!empty($this->options['total_pages'])) {
      if ($this->current_page >= $this->options['total_pages']) {
        $limit = $this->options['items_per_page'];
        $offset = $this->options['total_pages'] * $this->options['items_per_page'];
      }
    }

    $this->view->query->setLimit($limit);
    $this->view->query->setOffset($offset);
  }

  /**
   * Set the current page.
   *
   * @param $number
   *   If provided, the page number will be set to this. If NOT provided,
   *   the page number will be set from the global page array.
   */
  public function setCurrentPage($number = NULL) {
    if (isset($number)) {
      $this->current_page = max(0, $number);
      return;
    }

    // If the current page number was not specified, extract it from the global
    // page array.
    global $pager_page_array;

    if (empty($pager_page_array)) {
      $pager_page_array = [];
    }

    // Fill in missing values in the global page array, in case the global page
    // array hasn't been initialized before.
    $page = $this->view->getRequest()->query->get('page');
    $page = isset($page) ? explode(',', $page) : [];

    for ($i = 0; $i <= $this->options['id'] || $i < count($pager_page_array); $i++) {
      $pager_page_array[$i] = empty($page[$i]) ? 0 : $page[$i];
    }

    // Don't allow the number to be less than zero.
    $this->current_page = max(0, intval($pager_page_array[$this->options['id']]));
  }

  public function getPagerTotal() {
    if ($items_per_page = intval($this->getItemsPerPage())) {
      return ceil($this->total_items / $items_per_page);
    }
    else {
      return 1;
    }
  }

  /**
   * Update global paging info.
   *
   * This is called after the count query has been run to set the total
   * items available and to update the current page if the requested
   * page is out of range.
   */
  public function updatePageInfo() {
    if (!empty($this->options['total_pages'])) {
      if (($this->options['total_pages'] * $this->options['items_per_page']) < $this->total_items) {
        $this->total_items = $this->options['total_pages'] * $this->options['items_per_page'];
      }
    }

    // Don't set pager settings for items per page = 0.
    $items_per_page = $this->getItemsPerPage();
    if (!empty($items_per_page)) {
      // Dump information about what we already know into the globals.
      global $pager_page_array, $pager_total, $pager_total_items, $pager_limits;
      // Set the limit.
      $pager_limits[$this->options['id']] = $this->options['items_per_page'];
      // Set the item count for the pager.
      $pager_total_items[$this->options['id']] = $this->total_items;
      // Calculate and set the count of available pages.
      $pager_total[$this->options['id']] = $this->getPagerTotal();

      // See if the requested page was within range:
      if ($this->current_page >= $pager_total[$this->options['id']]) {
        // Pages are numbered from 0 so if there are 10 pages, the last page is 9.
        $this->setCurrentPage($pager_total[$this->options['id']] - 1);
      }

      // Put this number in to guarantee that we do not generate notices when the pager
      // goes to look for it later.
      $pager_page_array[$this->options['id']] = $this->current_page;
    }
  }

  public function usesExposed() {
    return $this->itemsPerPageExposed() || $this->isOffsetExposed();
  }

  protected function itemsPerPageExposed() {
    return !empty($this->options['expose']['items_per_page']);
  }

  protected function isOffsetExposed() {
    return !empty($this->options['expose']['offset']);
  }

  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    if ($this->itemsPerPageExposed()) {
      $options = explode(',', $this->options['expose']['items_per_page_options']);
      $sanitized_options = [];
      if (is_array($options)) {
        foreach ($options as $option) {
          $sanitized_options[intval($option)] = intval($option);
        }
        if (!empty($this->options['expose']['items_per_page_options_all']) && !empty($this->options['expose']['items_per_page_options_all_label'])) {
          $sanitized_options['All'] = $this->options['expose']['items_per_page_options_all_label'];
        }
        $form['items_per_page'] = [
          '#type' => 'select',
          '#title' => $this->options['expose']['items_per_page_label'],
          '#options' => $sanitized_options,
          '#default_value' => $this->getItemsPerPage(),
        ];
      }
    }

    if ($this->isOffsetExposed()) {
      $form['offset'] = [
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlength' => 10,
        '#title' => $this->options['expose']['offset_label'],
        '#default_value' => $this->getOffset(),
      ];
    }
  }

  public function exposedFormValidate(&$form, FormStateInterface $form_state) {
    if (!$form_state->isValueEmpty('offset') && trim($form_state->getValue('offset'))) {
      if (!is_numeric($form_state->getValue('offset')) || $form_state->getValue('offset') < 0) {
        $form_state->setErrorByName('offset', $this->t('Offset must be a number greater than or equal to 0.'));
      }
    }
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
    // The rendered link needs to play well with any other query parameter used
    // on the page, like other pagers and exposed filter.
    return ['url.query_args'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
