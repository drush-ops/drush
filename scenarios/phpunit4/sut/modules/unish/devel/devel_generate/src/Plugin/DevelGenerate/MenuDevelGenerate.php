<?php

namespace Drupal\devel_generate\Plugin\DevelGenerate;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MenuDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "menu",
 *   label = @Translation("menus"),
 *   description = @Translation("Generate a given number of menus and menu links. Optionally delete current menus."),
 *   url = "menu",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num_menus" = 2,
 *     "num_links" = 50,
 *     "title_length" = 12,
 *     "max_width" = 6,
 *     "kill" = FALSE,
 *   }
 * )
 */
class MenuDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuStorage;

  /**
   * The menu link storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuLinkContentStorage;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a MenuDevelGenerate object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $menu_storage
   *   The menu storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $menu_link_storage
   *   The menu storage.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, EntityStorageInterface $menu_storage, EntityStorageInterface $menu_link_storage, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->menuLinkTree = $menu_tree;
    $this->menuStorage = $menu_storage;
    $this->menuLinkContentStorage = $menu_link_storage;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('menu.link_tree'),
      $entity_manager->getStorage('menu'),
      $entity_manager->getStorage('menu_link_content'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $menu_enabled = $this->moduleHandler->moduleExists('menu_ui');
    if ($menu_enabled) {
      $menus = array('__new-menu__' => $this->t('Create new menu(s)')) + menu_ui_get_menus();
    }
    else {
      $menus = menu_list_system_menus();
    }
    $form['existing_menus'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Generate links for these menus'),
      '#options' => $menus,
      '#default_value' => array('__new-menu__'),
      '#required' => TRUE,
    );
    if ($menu_enabled) {
      $form['num_menus'] = array(
        '#type' => 'number',
        '#title' => $this->t('Number of new menus to create'),
        '#default_value' => $this->getSetting('num_menus'),
        '#min' => 0,
        '#states' => array(
          'visible' => array(
            ':input[name="existing_menus[__new-menu__]"]' => array('checked' => TRUE),
          ),
        ),
      );
    }
    $form['num_links'] = array(
      '#type' => 'number',
      '#title' => $this->t('Number of links to generate'),
      '#default_value' => $this->getSetting('num_links'),
      '#required' => TRUE,
      '#min' => 0,
    );
    $form['title_length'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum number of characters in menu and menu link names'),
      '#description' => $this->t('The minimum length is 2.'),
      '#default_value' => $this->getSetting('title_length'),
      '#required' => TRUE,
      '#min' => 2,
      '#max' => 128,
    );
    $form['link_types'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Types of links to generate'),
      '#options' => array(
        'node' => $this->t('Nodes'),
        'front' => $this->t('Front page'),
        'external' => $this->t('External'),
      ),
      '#default_value' => array('node', 'front', 'external'),
      '#required' => TRUE,
    );
    $form['max_depth'] = array(
      '#type' => 'select',
      '#title' => $this->t('Maximum link depth'),
      '#options' => range(0, $this->menuLinkTree->maxDepth()),
      '#default_value' => floor($this->menuLinkTree->maxDepth() / 2),
      '#required' => TRUE,
    );
    unset($form['max_depth']['#options'][0]);
    $form['max_width'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum menu width'),
      '#default_value' => $this->getSetting('max_width'),
      '#description' => $this->t('Limit the width of the generated menu\'s first level of links to a certain number of items.'),
      '#required' => TRUE,
      '#min' => 0,
    );
    $form['kill'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Delete existing custom generated menus and menu links before generating new ones.'),
      '#default_value' => $this->getSetting('kill'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function generateElements(array $values) {
    // If the create new menus checkbox is off, set the number of new menus to 0.
    if (!isset($values['existing_menus']['__new-menu__']) || !$values['existing_menus']['__new-menu__']) {
      $values['num_menus'] = 0;
    }
    else {
      // Unset the aux menu to avoid attach menu new items.
      unset($values['existing_menus']['__new-menu__']);
    }

    // Delete custom menus.
    if ($values['kill']) {
      $this->deleteMenus();
      $this->setMessage($this->t('Deleted existing menus and links.'));
    }

    // Generate new menus.
    $new_menus = $this->generateMenus($values['num_menus'], $values['title_length']);
    if (!empty($new_menus)) {
      $this->setMessage($this->t('Created the following new menus: @menus', array('@menus' => implode(', ', $new_menus))));
    }

    // Generate new menu links.
    $menus = $new_menus;
    if (isset($values['existing_menus'])) {
      $menus = $menus + $values['existing_menus'];
    }
    $new_links = $this->generateLinks($values['num_links'], $menus, $values['title_length'], $values['link_types'], $values['max_depth'], $values['max_width']);
    $this->setMessage($this->t('Created @count new menu links.', array('@count' => count($new_links))));
  }

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams($args, $options = []) {

    $link_types = array('node', 'front', 'external');
    $values = array(
      'num_menus' => array_shift($args),
      'num_links' => array_shift($args),
      'kill' => $this->isDrush8() ? drush_get_option('kill') : $options['kill'],
      'pipe' => $this->isDrush8() ? drush_get_option('pipe') : $options['pipe'],
      'link_types' => array_combine($link_types, $link_types),
    );

    $max_depth = array_shift($args);
    $max_width = array_shift($args);
    $values['max_depth'] = $max_depth ? $max_depth : 3;
    $values['max_width'] = $max_width ? $max_width : 8;
    $values['title_length'] = $this->getSetting('title_length');
    $values['existing_menus']['__new-menu__'] = TRUE;

    if ($this->isNumber($values['num_menus']) == FALSE) {
      throw new \Exception(dt('Invalid number of menus'));
    }
    if ($this->isNumber($values['num_links']) == FALSE) {
      throw new \Exception(dt('Invalid number of links'));
    }
    if ($this->isNumber($values['max_depth']) == FALSE || $values['max_depth'] > 9 || $values['max_depth'] < 1) {
      throw new \Exception(dt('Invalid maximum link depth. Use a value between 1 and 9'));
    }
    if ($this->isNumber($values['max_width']) == FALSE || $values['max_width'] < 1) {
      throw new \Exception(dt('Invalid maximum menu width. Use a positive numeric value.'));
    }

    return $values;
  }

  /**
   * Deletes custom generated menus.
   */
  protected function deleteMenus() {
    if ($this->moduleHandler->moduleExists('menu_ui')) {
      $menu_ids = array();
      foreach (menu_ui_get_menus(FALSE) as $menu => $menu_title) {
        if (strpos($menu, 'devel-') === 0) {
          $menu_ids[] = $menu;
        }
      }

      if ($menu_ids) {
        $menus = $this->menuStorage->loadMultiple($menu_ids);
        $this->menuStorage->delete($menus);
      }
    }

    // Delete menu links generated by devel.
    $link_ids = $this->menuLinkContentStorage->getQuery()
      ->condition('menu_name', 'devel', '<>')
      ->condition('link__options', '%' . db_like('s:5:"devel";b:1') . '%', 'LIKE')
      ->execute();

    if ($link_ids) {
      $links = $this->menuLinkContentStorage->loadMultiple($link_ids);
      $this->menuLinkContentStorage->delete($links);
    }

  }

  /**
   * Generates new menus.
   *
   * @param int $num_menus
   *   Number of menus to create.
   * @param int $title_length
   *   (optional) Maximum length per menu name.
   *
   * @return array
   *   Array containing the generated vocabularies id.
   */
  protected function generateMenus($num_menus, $title_length = 12) {
    $menus = array();

    for ($i = 1; $i <= $num_menus; $i++) {
      $name = $this->getRandom()->word(mt_rand(2, max(2, $title_length)));

      $menu = $this->menuStorage->create(array(
        'label' => $name,
        'id' => 'devel-' . Unicode::strtolower($name),
        'description' => $this->t('Description of @name', array('@name' => $name)),
      ));

      $menu->save();
      $menus[$menu->id()] = $menu->label();
    }

    return $menus;
  }

  /**
   * Generates menu links in a tree structure.
   */
  protected function generateLinks($num_links, $menus, $title_length, $link_types, $max_depth, $max_width) {
    $links = array();
    $menus = array_keys(array_filter($menus));
    $link_types = array_keys(array_filter($link_types));

    $nids = array();
    for ($i = 1; $i <= $num_links; $i++) {
      // Pick a random menu.
      $menu_name = $menus[array_rand($menus)];
      // Build up our link.
      $link_title = $this->getRandom()->word(mt_rand(2, max(2, $title_length)));
      $link = $this->menuLinkContentStorage->create(array(
        'menu_name' => $menu_name,
        'weight' => mt_rand(-50, 50),
        'title' => $link_title,
        'bundle' => 'menu_link_content',
        'description' => $this->t('Description of @title.', array('@title' => $link_title)),
      ));
      $link->link->options = array('devel' => TRUE);

      // For the first $max_width items, make first level links.
      if ($i <= $max_width) {
        $depth = 0;
      }
      else {
        // Otherwise, get a random parent menu depth.
        $depth = mt_rand(1, max(1, $max_depth - 1));
      }
      // Get a random parent link from the proper depth.
      do {
        $parameters = new MenuTreeParameters();
        $parameters->setMinDepth($depth);
        $parameters->setMaxDepth($depth);
        $tree = $this->menuLinkTree->load($menu_name, $parameters);

        if ($tree) {
          $link->parent = array_rand($tree);
        }
        $depth--;
      } while (!$link->parent && $depth > 0);

      $link_type = array_rand($link_types);
      switch ($link_types[$link_type]) {
        case 'node':
          // Grab a random node ID.
          $select = db_select('node_field_data', 'n')
            ->fields('n', array('nid', 'title'))
            ->condition('n.status', 1)
            ->range(0, 1)
            ->orderRandom();
          // Don't put a node into the menu twice.
          if (!empty($nids[$menu_name])) {
            $select->condition('n.nid', $nids[$menu_name], 'NOT IN');
          }
          $node = $select->execute()->fetchAssoc();
          if (isset($node['nid'])) {
            $nids[$menu_name][] = $node['nid'];
            $link->link->uri = 'entity:node/' . $node['nid'];
            $link->title = $node['title'];
            break;
          }

        case 'external':
          $link->link->uri = 'http://www.example.com/';
          break;

        case 'front':
          $link->link->uri = 'internal:/<front>';
          break;

        default:
          $link->devel_link_type = $link_type;
          break;
      }

      $link->save();

      $links[$link->id()] = $link->link_title;
    }

    return $links;
  }

}
