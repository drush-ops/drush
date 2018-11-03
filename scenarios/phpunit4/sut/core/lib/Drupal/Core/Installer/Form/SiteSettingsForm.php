<?php

namespace Drupal\Core\Installer\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to configure and rewrite settings.php.
 *
 * @internal
 */
class SiteSettingsForm extends FormBase {

  /**
   * The site path.
   *
   * @var string
   */
  protected $sitePath;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new SiteSettingsForm.
   *
   * @param string $site_path
   *   The site path.
   */
  public function __construct($site_path, RendererInterface $renderer) {
    $this->sitePath = $site_path;
    $this->renderer = $renderer;
  }

  /**
    * {@inheritdoc}
    */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('site.path'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'install_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings_file = './' . $this->sitePath . '/settings.php';

    $form['#title'] = $this->t('Database configuration');

    $drivers = drupal_get_database_types();
    $drivers_keys = array_keys($drivers);

    // Unless there is input for this form (for a non-interactive installation,
    // input originates from the $settings array passed into install_drupal()),
    // check whether database connection settings have been prepared in
    // settings.php already.
    // Note: The installer even executes this form if there is a valid database
    // connection already, since the submit handler of this form is responsible
    // for writing all $settings to settings.php (not limited to $databases).
    $input = &$form_state->getUserInput();
    if (!isset($input['driver']) && $database = Database::getConnectionInfo()) {
      $input['driver'] = $database['default']['driver'];
      $input[$database['default']['driver']] = $database['default'];
    }

    if (isset($input['driver'])) {
      $default_driver = $input['driver'];
      // In case of database connection info from settings.php, as well as for a
      // programmed form submission (non-interactive installer), the table prefix
      // information is usually normalized into an array already, but the form
      // element only allows to configure one default prefix for all tables.
      $prefix = &$input[$default_driver]['prefix'];
      if (isset($prefix) && is_array($prefix)) {
        $prefix = $prefix['default'];
      }
      $default_options = $input[$default_driver];
    }
    // If there is no database information yet, suggest the first available driver
    // as default value, so that its settings form is made visible via #states
    // when JavaScript is enabled (see below).
    else {
      $default_driver = current($drivers_keys);
      $default_options = [];
    }

    $form['driver'] = [
      '#type' => 'radios',
      '#title' => $this->t('Database type'),
      '#required' => TRUE,
      '#default_value' => $default_driver,
    ];
    if (count($drivers) == 1) {
      $form['driver']['#disabled'] = TRUE;
    }

    // Add driver specific configuration options.
    foreach ($drivers as $key => $driver) {
      $form['driver']['#options'][$key] = $driver->name();

      $form['settings'][$key] = $driver->getFormOptions($default_options);
      $form['settings'][$key]['#prefix'] = '<h2 class="js-hide">' . $this->t('@driver_name settings', ['@driver_name' => $driver->name()]) . '</h2>';
      $form['settings'][$key]['#type'] = 'container';
      $form['settings'][$key]['#tree'] = TRUE;
      $form['settings'][$key]['advanced_options']['#parents'] = [$key];
      $form['settings'][$key]['#states'] = [
        'visible' => [
          ':input[name=driver]' => ['value' => $key],
        ],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#button_type' => 'primary',
      '#limit_validation_errors' => [
        ['driver'],
        [$default_driver],
      ],
      '#submit' => ['::submitForm'],
    ];

    $form['errors'] = [];
    $form['settings_file'] = ['#type' => 'value', '#value' => $settings_file];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $driver = $form_state->getValue('driver');
    $database = $form_state->getValue($driver);
    $drivers = drupal_get_database_types();
    $reflection = new \ReflectionClass($drivers[$driver]);
    $install_namespace = $reflection->getNamespaceName();
    // Cut the trailing \Install from namespace.
    $database['namespace'] = substr($install_namespace, 0, strrpos($install_namespace, '\\'));
    $database['driver'] = $driver;

    $form_state->set('database', $database);
    foreach ($this->getDatabaseErrors($database, $form_state->getValue('settings_file')) as $name => $message) {
      $form_state->setErrorByName($name, $message);
    }
  }

  /**
   * Get any database errors and links them to a form element.
   *
   * @param array $database
   *   An array of database settings.
   * @param string $settings_file
   *   The settings file that contains the database settings.
   *
   * @return array
   *   An array of form errors keyed by the element name and parents.
   */
  protected function getDatabaseErrors(array $database, $settings_file) {
    $errors = install_database_errors($database, $settings_file);
    $form_errors = array_filter($errors, function ($value) {
      // Errors keyed by something other than an integer already are linked to
      // form elements.
      return is_int($value);
    });

    // Find the generic errors.
    $errors = array_diff_key($errors, $form_errors);

    if (count($errors)) {
      $error_message = [
        '#type' => 'inline_template',
        '#template' => '{% trans %}Resolve all issues below to continue the installation. For help configuring your database server, see the <a href="https://www.drupal.org/getting-started/install">installation handbook</a>, or contact your hosting provider.{% endtrans%}{{ errors }}',
        '#context' => [
          'errors' => [
            '#theme' => 'item_list',
            '#items' => $errors,
          ],
        ],
      ];

      // These are generic errors, so we do not have any specific key of the
      // database connection array to attach them to; therefore, we just put
      // them in the error array with standard numeric keys.
      $form_errors[$database['driver'] . '][0'] = $this->renderer->renderPlain($error_message);
    }

    return $form_errors;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $install_state;

    // Update global settings array and save.
    $settings = [];
    $database = $form_state->get('database');
    $settings['databases']['default']['default'] = (object) [
      'value'    => $database,
      'required' => TRUE,
    ];
    $settings['settings']['hash_salt'] = (object) [
      'value'    => Crypt::randomBytesBase64(55),
      'required' => TRUE,
    ];

    drupal_rewrite_settings($settings);

    // Add the config directories to settings.php.
    drupal_install_config_directories();

    // Indicate that the settings file has been verified, and check the database
    // for the last completed task, now that we have a valid connection. This
    // last step is important since we want to trigger an error if the new
    // database already has Drupal installed.
    $install_state['settings_verified'] = TRUE;
    $install_state['config_verified'] = TRUE;
    $install_state['database_verified'] = TRUE;
    $install_state['completed_task'] = install_verify_completed_task();
  }

}
