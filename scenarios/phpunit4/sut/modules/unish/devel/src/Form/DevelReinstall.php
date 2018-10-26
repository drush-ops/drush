<?php

namespace Drupal\devel\Form;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display a dropdown of installed modules with the option to reinstall them.
 */
class DevelReinstall extends FormBase {

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * Constructs a new DevelReinstall form.
   *
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   */
  public function __construct(ModuleInstallerInterface $module_installer) {
    $this->moduleInstaller = $module_installer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_installer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devel_reinstall_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get a list of all available modules.
    $modules = system_rebuild_module_data();

    $uninstallable = array_filter($modules, function ($module) use ($modules) {
      return empty($modules[$module->getName()]->info['required']) && drupal_get_installed_schema_version($module->getName()) > SCHEMA_UNINSTALLED && $module->getName() !== 'devel';
    });

    $form['filters'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('table-filter', 'js-show'),
      ),
    );
    $form['filters']['text'] = array(
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Enter module name'),
      '#attributes' => array(
        'class' => array('table-filter-text'),
        'data-table' => '#devel-reinstall-form',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the module name or description to filter by.'),
      ),
    );

    // Only build the rest of the form if there are any modules available to
    // uninstall;
    if (empty($uninstallable)) {
      return $form;
    }

    $header = array(
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
    );

    $rows = array();

    foreach ($uninstallable as $module) {
      $name = $module->info['name'] ? : $module->getName();

      $rows[$module->getName()] = array(
        'name' => array(
          'data' => array(
            '#type' => 'inline_template',
            '#template' => '<label class="module-name table-filter-text-source">{{ module_name }}</label>',
            '#context' => array('module_name' => $name),
          )
        ),
        'description' => array(
          'data' => $module->info['description'],
          'class' => array('description'),
        ),
      );
    }

    $form['reinstall'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#js_select' => FALSE,
      '#empty' => $this->t('No modules are available to uninstall.'),
    );

    $form['#attached']['library'][] = 'system/drupal.system.modules';

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Reinstall'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Form submitted, but no modules selected.
    if (!array_filter($form_state->getValue('reinstall'))) {
      $form_state->setErrorByName('reinstall', $this->t('No modules selected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $modules = $form_state->getValue('reinstall');
      $reinstall = array_keys(array_filter($modules));
      $this->moduleInstaller->uninstall($reinstall, FALSE);
      $this->moduleInstaller->install($reinstall, FALSE);
      drupal_set_message($this->t('Uninstalled and installed: %names.', array('%names' => implode(', ', $reinstall))));
    }
    catch (\Exception $e) {
      drupal_set_message($this->t('Unable to reinstall modules. Error: %error.', array('%error' => $e->getMessage())), 'error');
    }
  }

}
