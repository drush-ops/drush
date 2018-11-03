<?php

namespace Drupal\media\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\media\IFrameUrlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to configure Media settings.
 *
 * @internal
 */
class MediaSettingsForm extends ConfigFormBase {

  /**
   * The iFrame URL helper service.
   *
   * @var \Drupal\media\IFrameUrlHelper
   */
  protected $iFrameUrlHelper;

  /**
   * MediaSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\media\IFrameUrlHelper $iframe_url_helper
   *   The iFrame URL helper service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, IFrameUrlHelper $iframe_url_helper) {
    parent::__construct($config_factory);
    $this->iFrameUrlHelper = $iframe_url_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('media.oembed.iframe_url_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['media.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $domain = $this->config('media.settings')->get('iframe_domain');

    if (!$this->iFrameUrlHelper->isSecure($domain)) {
      $message = $this->t('It is potentially insecure to display oEmbed content in a frame that is served from the same domain as your main Drupal site, as this may allow execution of third-party code. <a href="https://oembed.com/#section3" target="_blank">Take a look here for more information</a>.');
      $this->messenger()->addWarning($message);
    }

    $description = '<p>' . $this->t('Displaying media assets from third-party services, such as YouTube or Twitter, can be risky. This is because many of these services return arbitrary HTML to represent those assets, and that HTML may contain executable JavaScript code. If handled improperly, this can increase the risk of your site being compromised.') . '</p>';
    $description .= '<p>' . $this->t('In order to mitigate the risks, third-party assets are displayed in an iFrame, which effectively sandboxes any executable code running inside it. For even more security, the iFrame can be served from an alternate domain (that also points to your Drupal site), which you can configure on this page. This helps safeguard cookies and other sensitive information.') . '</p>';

    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security'),
      '#description' => $description,
      '#open' => TRUE,
    ];
    // @todo Figure out how and if we should validate that this domain actually
    // points back to Drupal.
    // See https://www.drupal.org/project/drupal/issues/2965979 for more info.
    $form['security']['iframe_domain'] = [
      '#type' => 'url',
      '#title' => $this->t('iFrame domain'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $domain,
      '#description' => $this->t('Enter a different domain from which to serve oEmbed content, including the <em>http://</em> or <em>https://</em> prefix. This domain needs to point back to this site, or existing oEmbed content may not display correctly, or at all.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('media.settings')
      ->set('iframe_domain', $form_state->getValue('iframe_domain'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
