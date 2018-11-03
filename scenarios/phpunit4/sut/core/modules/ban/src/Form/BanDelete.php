<?php

namespace Drupal\ban\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\ban\BanIpManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form to unban IP addresses.
 *
 * @internal
 */
class BanDelete extends ConfirmFormBase {

  /**
   * The banned IP address.
   *
   * @var string
   */
  protected $banIp;

  /**
   * The IP manager.
   *
   * @var \Drupal\ban\BanIpManagerInterface
   */
  protected $ipManager;

  /**
   * Constructs a new BanDelete object.
   *
   * @param \Drupal\ban\BanIpManagerInterface $ip_manager
   *   The IP manager.
   */
  public function __construct(BanIpManagerInterface $ip_manager) {
    $this->ipManager = $ip_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ban.ip_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ban_ip_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to unblock %ip?', ['%ip' => $this->banIp]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('ban.admin_page');
  }

  /**
   * {@inheritdoc}
   *
   * @param string $ban_id
   *   The IP address record ID to unban.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ban_id = '') {
    if (!$this->banIp = $this->ipManager->findById($ban_id)) {
      throw new NotFoundHttpException();
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->ipManager->unbanIp($this->banIp);
    $this->logger('user')->notice('Deleted %ip', ['%ip' => $this->banIp]);
    $this->messenger()->addStatus($this->t('The IP address %ip was deleted.', ['%ip' => $this->banIp]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
