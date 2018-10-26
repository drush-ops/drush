<?php

namespace Drupal\devel\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for switching users.
 *
 * @Block(
 *   id = "devel_switch_user",
 *   admin_label = @Translation("Switch user"),
 *   category = @Translation("Forms")
 * )
 */
class SwitchUserBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use RedirectDestinationTrait;

  /**
   * The FormBuilder object.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The Current User object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a new SwitchUserBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Entity\EntityStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, EntityStorageInterface $user_storage, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'list_size' => 12,
      'include_anon' => FALSE,
      'show_form' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'switch users');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $anononymous = new AnonymousUserSession();
    $form['list_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of users to display in the list'),
      '#default_value' => $this->configuration['list_size'],
      '#min' => 1,
      '#max' => 50,
    ];
    $form['include_anon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include %anonymous', ['%anonymous' => $anononymous->getAccountName()]),
      '#default_value' => $this->configuration['include_anon'],
    ];
    $form['show_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow entering any user name'),
      '#default_value' => $this->configuration['show_form'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['list_size'] = $form_state->getValue('list_size');
    $this->configuration['include_anon'] = $form_state->getValue('include_anon');
    $this->configuration['show_form'] = $form_state->getValue('show_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    if ($accounts = $this->getUsers()) {
      $build['devel_links'] = $this->buildUserList($accounts);

      if ($this->configuration['show_form']) {
        $build['devel_form'] = $this->formBuilder->getForm('\Drupal\devel\Form\SwitchUserForm');
      }
    }

    return $build;
  }

  /**
   * Provides the list of accounts that can be used for the user switch.
   *
   * Inactive users are omitted from all of the following db selects. Users
   * with 'switch users' permission and anonymous user if include_anon property
   * is set to TRUE, are prioritized.
   *
   * @return \Drupal\core\Session\AccountInterface[]
   *   List of accounts to be used for the switch.
   */
  protected function getUsers() {
    $list_size = $this->configuration['list_size'];
    $include_anonymous = $this->configuration['include_anon'];

    $list_size = $include_anonymous ? $list_size - 1 : $list_size;

    // Users with 'switch users' permission are prioritized so
    // we try to load first users with this permission.
    $query = $this->userStorage->getQuery()
      ->condition('uid', 0, '>')
      ->condition('status', 0, '>')
      ->sort('access', 'DESC')
      ->range(0, $list_size);

    $roles = user_roles(TRUE, 'switch users');

    if (!empty($roles) && !isset($roles[Role::AUTHENTICATED_ID])) {
      $query->condition('roles', array_keys($roles), 'IN');
    }

    $user_ids = $query->execute();

    // If we don't have enough users with 'switch users' permission, add
    // uids until we hit $list_size.
    if (count($user_ids) < $list_size) {
      $query = $this->userStorage->getQuery()
        ->condition('uid', 0, '>')
        ->condition('status', 0, '>')
        ->sort('access', 'DESC')
        ->range(0, $list_size);

      // Excludes the prioritized user ids only if the previous query return
      // some records.
      if (!empty($user_ids)) {
        $query->condition('uid', array_keys($user_ids), 'NOT IN');
        $query->range(0, $list_size - count($user_ids));
      }

      $user_ids += $query->execute();
    }

    $accounts = $this->userStorage->loadMultiple($user_ids);

    if ($include_anonymous) {
      $anonymous = new AnonymousUserSession();
      $accounts[$anonymous->id()] = $anonymous;
    }

    uasort($accounts, 'static::sortUserList');

    return $accounts;
  }

  /**
   * Builds the user listing as renderable array.
   *
   * @param \Drupal\core\Session\AccountInterface[] $accounts
   *   The accounts to be rendered in the list.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildUserList(array $accounts) {
    $links = [];

    foreach ($accounts as $account) {
      $links[$account->id()] = [
        'title' => $account->getDisplayName(),
        'url' => Url::fromRoute('devel.switch', ['name' => $account->getAccountName()]),
        'query' => $this->getDestinationArray(),
        'attributes' => [
          'title' => $account->hasPermission('switch users') ? $this->t('This user can switch back.') : $this->t('Caution: this user will be unable to switch back.'),
        ],
      ];

      if ($account->isAnonymous()) {
        $links[$account->id()]['url'] = Url::fromRoute('user.logout');
      }

      if ($this->currentUser->id() === $account->id()) {
        $links[$account->id()]['title'] = new FormattableMarkup('<strong>%user</strong>', ['%user' => $account->getDisplayName()]);
      }
    }

    return [
      '#theme' => 'links',
      '#links' => $links,
      '#attached' => ['library' => ['devel/devel']],
    ];
  }

  /**
   * Helper callback for uasort() to sort accounts by last access.
   */
  public static function sortUserList(AccountInterface $a, AccountInterface $b) {
    $a_access = (int) $a->getLastAccessedTime();
    $b_access = (int) $b->getLastAccessedTime();

    if ($a_access === $b_access) {
      return 0;
    }

    // User never access to site.
    if ($a_access === 0) {
      return 1;
    }

    return ($a_access > $b_access) ? -1 : 1;
  }

}
