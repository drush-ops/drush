<?php

namespace Drupal\devel\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures devel toolbar settings.
 */
class ToolbarSettingsForm extends ConfigFormBase {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  protected $menuLinkTree;

  /**
   * ToolbarSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MenuLinkTreeInterface $menu_link_tree) {
    parent::__construct($config_factory);
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('menu.link_tree')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devel_toolbar_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'devel.toolbar.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('devel.toolbar.settings');

    $form['toolbar_items'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Menu items always visible'),
      '#options' => $this->getLinkLabels(),
      '#default_value' => $config->get('toolbar_items') ?: [],
      '#required' => TRUE,
      '#description' => $this->t('Select the menu items always visible in devel toolbar tray. All the items not selected in this list will be visible only when the toolbar orientation is vertical.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $toolbar_items = array_keys(array_filter($values['toolbar_items']));

    $this->config('devel.toolbar.settings')
      ->set('toolbar_items', $toolbar_items)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Provides an array of available menu items.
   *
   * @return array
   *   Associative array of devel menu item labels keyed by plugin ID.
   */
  protected function getLinkLabels() {
    $options = [];

    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks()->setTopLevelOnly();
    $tree = $this->menuLinkTree->load('devel', $parameters);

    foreach ($tree as $element) {
      $link = $element->link;
      $options[$link->getPluginId()] = $link->getTitle();
    }

    asort($options);

    return $options;
  }

}
