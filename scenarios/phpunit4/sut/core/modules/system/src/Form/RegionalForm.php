<?php

namespace Drupal\system\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure regional settings for this site.
 *
 * @internal
 */
class RegionalForm extends ConfigFormBase {

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * Constructs a RegionalForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CountryManagerInterface $country_manager) {
    parent::__construct($config_factory);
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('country_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_regional_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['system.date'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $countries = $this->countryManager->getList();
    $system_date = $this->config('system.date');

    // Date settings:
    $zones = system_time_zones(NULL, TRUE);

    $form['locale'] = [
      '#type' => 'details',
      '#title' => t('Locale'),
      '#open' => TRUE,
    ];

    $form['locale']['site_default_country'] = [
      '#type' => 'select',
      '#title' => t('Default country'),
      '#empty_value' => '',
      '#default_value' => $system_date->get('country.default'),
      '#options' => $countries,
      '#attributes' => ['class' => ['country-detect']],
    ];

    $form['locale']['date_first_day'] = [
      '#type' => 'select',
      '#title' => t('First day of week'),
      '#default_value' => $system_date->get('first_day'),
      '#options' => [0 => t('Sunday'), 1 => t('Monday'), 2 => t('Tuesday'), 3 => t('Wednesday'), 4 => t('Thursday'), 5 => t('Friday'), 6 => t('Saturday')],
    ];

    $form['timezone'] = [
      '#type' => 'details',
      '#title' => t('Time zones'),
      '#open' => TRUE,
    ];

    $form['timezone']['date_default_timezone'] = [
      '#type' => 'select',
      '#title' => t('Default time zone'),
      '#default_value' => $system_date->get('timezone.default') ?: date_default_timezone_get(),
      '#options' => $zones,
    ];

    $configurable_timezones = $system_date->get('timezone.user.configurable');
    $form['timezone']['configurable_timezones'] = [
      '#type' => 'checkbox',
      '#title' => t('Users may set their own time zone'),
      '#default_value' => $configurable_timezones,
    ];

    $form['timezone']['configurable_timezones_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        // Hide the user configured timezone settings when users are forced to use
        // the default setting.
        'invisible' => [
          'input[name="configurable_timezones"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['timezone']['configurable_timezones_wrapper']['empty_timezone_message'] = [
      '#type' => 'checkbox',
      '#title' => t('Remind users at login if their time zone is not set'),
      '#default_value' => $system_date->get('timezone.user.warn'),
      '#description' => t('Only applied if users may set their own time zone.'),
    ];

    $form['timezone']['configurable_timezones_wrapper']['user_default_timezone'] = [
      '#type' => 'radios',
      '#title' => t('Time zone for new users'),
      '#default_value' => $system_date->get('timezone.user.default'),
      '#options' => [
        DRUPAL_USER_TIMEZONE_DEFAULT => t('Default time zone'),
        DRUPAL_USER_TIMEZONE_EMPTY   => t('Empty time zone'),
        DRUPAL_USER_TIMEZONE_SELECT  => t('Users may set their own time zone at registration'),
      ],
      '#description' => t('Only applied if users may set their own time zone.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('system.date')
      ->set('country.default', $form_state->getValue('site_default_country'))
      ->set('first_day', $form_state->getValue('date_first_day'))
      ->set('timezone.default', $form_state->getValue('date_default_timezone'))
      ->set('timezone.user.configurable', $form_state->getValue('configurable_timezones'))
      ->set('timezone.user.warn', $form_state->getValue('empty_timezone_message'))
      ->set('timezone.user.default', $form_state->getValue('user_default_timezone'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
