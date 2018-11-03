<?php

namespace Drupal\book\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\book\BookManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Provides a 'Book navigation' block.
 *
 * @Block(
 *   id = "book_navigation",
 *   admin_label = @Translation("Book navigation"),
 *   category = @Translation("Menus")
 * )
 */
class BookNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The book manager.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected $bookManager;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a new BookNavigationBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack object.
   * @param \Drupal\book\BookManagerInterface $book_manager
   *   The book manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, BookManagerInterface $book_manager, EntityStorageInterface $node_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->requestStack = $request_stack;
    $this->bookManager = $book_manager;
    $this->nodeStorage = $node_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('book.manager'),
      $container->get('entity.manager')->getStorage('node')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'block_mode' => "all pages",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $options = [
      'all pages' => $this->t('Show block on all pages'),
      'book pages' => $this->t('Show block only on book pages'),
    ];
    $form['book_block_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Book navigation block display'),
      '#options' => $options,
      '#default_value' => $this->configuration['block_mode'],
      '#description' => $this->t("If <em>Show block on all pages</em> is selected, the block will contain the automatically generated menus for all of the site's books. If <em>Show block only on book pages</em> is selected, the block will contain only the one menu corresponding to the current page's book. In this case, if the current page is not in a book, no block will be displayed. The <em>Page specific visibility settings</em> or other visibility settings can be used in addition to selectively display this block."),
      ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['block_mode'] = $form_state->getValue('book_block_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_bid = 0;

    if ($node = $this->requestStack->getCurrentRequest()->get('node')) {
      $current_bid = empty($node->book['bid']) ? 0 : $node->book['bid'];
    }
    if ($this->configuration['block_mode'] == 'all pages') {
      $book_menus = [];
      $pseudo_tree = [0 => ['below' => FALSE]];
      foreach ($this->bookManager->getAllBooks() as $book_id => $book) {
        if ($book['bid'] == $current_bid) {
          // If the current page is a node associated with a book, the menu
          // needs to be retrieved.
          $data = $this->bookManager->bookTreeAllData($node->book['bid'], $node->book);
          $book_menus[$book_id] = $this->bookManager->bookTreeOutput($data);
        }
        else {
          // Since we know we will only display a link to the top node, there
          // is no reason to run an additional menu tree query for each book.
          $book['in_active_trail'] = FALSE;
          // Check whether user can access the book link.
          $book_node = $this->nodeStorage->load($book['nid']);
          $book['access'] = $book_node->access('view');
          $pseudo_tree[0]['link'] = $book;
          $book_menus[$book_id] = $this->bookManager->bookTreeOutput($pseudo_tree);
        }
        $book_menus[$book_id] += [
          '#book_title' => $book['title'],
        ];
      }
      if ($book_menus) {
        return [
          '#theme' => 'book_all_books_block',
        ] + $book_menus;
      }
    }
    elseif ($current_bid) {
      // Only display this block when the user is browsing a book and do
      // not show unpublished books.
      $nid = \Drupal::entityQuery('node')
        ->condition('nid', $node->book['bid'], '=')
        ->condition('status', NodeInterface::PUBLISHED)
        ->execute();

      // Only show the block if the user has view access for the top-level node.
      if ($nid) {
        $tree = $this->bookManager->bookTreeAllData($node->book['bid'], $node->book);
        // There should only be one element at the top level.
        $data = array_shift($tree);
        $below = $this->bookManager->bookTreeOutput($data['below']);
        if (!empty($below)) {
          return $below;
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.book_navigation']);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Make cacheable in https://www.drupal.org/node/2483181
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
