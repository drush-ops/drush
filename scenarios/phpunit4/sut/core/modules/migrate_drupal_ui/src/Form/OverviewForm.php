<?php

namespace Drupal\migrate_drupal_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrate Upgrade Overview form.
 *
 * @internal
 */
class OverviewForm extends MigrateUpgradeFormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Overview form constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore_private
   *   The private tempstore factory.
   */
  public function __construct(StateInterface $state, PrivateTempStoreFactory $tempstore_private) {
    parent::__construct($tempstore_private);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_drupal_ui_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // If an upgrade has already been performed, redirect to the incremental
    // form.
    if ($this->state->get('migrate_drupal_ui.performed')) {
      $this->store->set('step', 'incremental');
      return $this->redirect('migrate_drupal_ui.upgrade_incremental');
    }

    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Upgrade');

    $form['info_header'] = [
      '#markup' => '<p>' . $this->t('Upgrade a site by importing its files and the data from its database into a clean and empty new install of Drupal 8. See the <a href=":url">Drupal site upgrades handbook</a> for more information.', [
          ':url' => 'https://www.drupal.org/upgrade/migrate',
        ]),
    ];

    $form['legend']['#markup'] = '';
    $form['legend']['#markup'] .= '<h3>' . $this->t('Definitions') . '</h3>';
    $form['legend']['#markup'] .= '<dl>';
    $form['legend']['#markup'] .= '<dt>' . $this->t('Old site') . '</dt>';
    $form['legend']['#markup'] .= '<dd>' . $this->t('The site you want to upgrade.') . '</dd>';
    $form['legend']['#markup'] .= '<dt>' . $this->t('New site') . '</dt>';
    $form['legend']['#markup'] .= '<dd>' . $this->t('This empty Drupal 8 installation you will import the old site to.') . '</dd>';
    $form['legend']['#markup'] .= '</dl>';

    $info[] = $this->t('Make sure that <strong>access to the database</strong> for the old site is available from this new site.');
    $info[] = $this->t('<strong>If the old site has private files</strong>, a copy of its files directory must also be accessible on the host of this new site.');
    $info[] = $this->t('<strong>Enable all modules on this new site</strong> that are enabled on the old site. For example, if the old site uses the book module, then enable the book module on this new site so that the existing data can be imported to it.');
    $info[] = $this->t('<strong>Do not add any content to the new site</strong> before upgrading. Any existing content is likely to be overwritten by the upgrade process. See <a href=":url">the upgrade preparation guide</a>.', [
      ':url' => 'https://www.drupal.org/docs/8/upgrade/preparing-an-upgrade#dont_create_content',
    ]);
    $info[] = $this->t('Put this site into <a href=":url">maintenance mode</a>.', [
      ':url' => Url::fromRoute('system.site_maintenance_mode')
        ->toString(TRUE)
        ->getGeneratedUrl(),
    ]);

    $form['info'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Preparation steps'),
      '#list_type' => 'ol',
      '#items' => $info,
    ];

    $form['info_footer'] = [
      '#markup' => '<p>' . $this->t('The upgrade can take a long time. It is better to upgrade from a local copy of your site instead of directly from your live site.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('step', 'credential');
    $form_state->setRedirect('migrate_drupal_ui.upgrade_credential');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Continue');
  }

}
