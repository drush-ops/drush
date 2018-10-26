<?php

namespace Drupal\devel_generate\Plugin\DevelGenerate;

use Drupal\comment\CommentManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\devel_generate\DevelGenerateBase;
use Drupal\field\Entity\FieldConfig;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ContentDevelGenerate plugin.
 *
 * @DevelGenerate(
 *   id = "content",
 *   label = @Translation("content"),
 *   description = @Translation("Generate a given number of content. Optionally delete current content."),
 *   url = "content",
 *   permission = "administer devel_generate",
 *   settings = {
 *     "num" = 50,
 *     "kill" = FALSE,
 *     "max_comments" = 0,
 *     "title_length" = 4
 *   }
 * )
 */
class ContentDevelGenerate extends DevelGenerateBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The node type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeTypeStorage;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The comment manager service.
   *
   * @var \Drupal\comment\CommentManagerInterface
   */
  protected $commentManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_type_storage
   *   The node type storage.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\comment\CommentManagerInterface $comment_manager
   *   The comment manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityStorageInterface $node_storage, EntityStorageInterface $node_type_storage, ModuleHandlerInterface $module_handler, CommentManagerInterface $comment_manager = NULL, LanguageManagerInterface $language_manager, UrlGeneratorInterface $url_generator, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->moduleHandler = $module_handler;
    $this->nodeStorage = $node_storage;
    $this->nodeTypeStorage = $node_type_storage;
    $this->commentManager = $comment_manager;
    $this->languageManager = $language_manager;
    $this->urlGenerator = $url_generator;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $entity_manager->getStorage('node'),
      $entity_manager->getStorage('node_type'),
      $container->get('module_handler'),
      $container->has('comment.manager') ? $container->get('comment.manager') : NULL,
      $container->get('language_manager'),
      $container->get('url_generator'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $types = $this->nodeTypeStorage->loadMultiple();

    if (empty($types)) {
      $create_url = $this->urlGenerator->generateFromRoute('node.type_add');
      $this->setMessage($this->t('You do not have any content types that can be generated. <a href=":create-type">Go create a new content type</a>', array(':create-type' => $create_url)), 'error', FALSE);
      return;
    }

    $options = array();

    foreach ($types as $type) {
      $options[$type->id()] = array(
        'type' => array('#markup' => $type->label()),
      );
      if ($this->commentManager) {
        $comment_fields = $this->commentManager->getFields('node');
        $map = array($this->t('Hidden'), $this->t('Closed'), $this->t('Open'));

        $fields = array();
        foreach ($comment_fields as $field_name => $info) {
          // Find all comment fields for the bundle.
          if (in_array($type->id(), $info['bundles'])) {
            $instance = FieldConfig::loadByName('node', $type->id(), $field_name);
            $default_value = $instance->getDefaultValueLiteral();
            $default_mode = reset($default_value);
            $fields[] = new FormattableMarkup('@field: @state', array(
              '@field' => $instance->label(),
              '@state' => $map[$default_mode['status']],
            ));
          }
        }
        // @todo Refactor display of comment fields.
        if (!empty($fields)) {
          $options[$type->id()]['comments'] = array(
            'data' => array(
              '#theme' => 'item_list',
              '#items' => $fields,
            ),
          );
        }
        else {
          $options[$type->id()]['comments'] = $this->t('No comment fields');
        }
      }
    }

    $header = array(
      'type' => $this->t('Content type'),
    );
    if ($this->commentManager) {
      $header['comments'] = array(
        'data' => $this->t('Comments'),
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
      );
    }

    $form['node_types'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
    );

    $form['kill'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Delete all content</strong> in these content types before generating new content.'),
      '#default_value' => $this->getSetting('kill'),
    );
    $form['num'] = array(
      '#type' => 'number',
      '#title' => $this->t('How many nodes would you like to generate?'),
      '#default_value' => $this->getSetting('num'),
      '#required' => TRUE,
      '#min' => 0,
    );

    $options = array(1 => $this->t('Now'));
    foreach (array(3600, 86400, 604800, 2592000, 31536000) as $interval) {
      $options[$interval] = $this->dateFormatter->formatInterval($interval, 1) . ' ' . $this->t('ago');
    }
    $form['time_range'] = array(
      '#type' => 'select',
      '#title' => $this->t('How far back in time should the nodes be dated?'),
      '#description' => $this->t('Node creation dates will be distributed randomly from the current time, back to the selected time.'),
      '#options' => $options,
      '#default_value' => 604800,
    );

    $form['max_comments'] = array(
      '#type' => $this->moduleHandler->moduleExists('comment') ? 'number' : 'value',
      '#title' => $this->t('Maximum number of comments per node.'),
      '#description' => $this->t('You must also enable comments for the content types you are generating. Note that some nodes will randomly receive zero comments. Some will receive the max.'),
      '#default_value' => $this->getSetting('max_comments'),
      '#min' => 0,
      '#access' => $this->moduleHandler->moduleExists('comment'),
    );
    $form['title_length'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum number of words in titles'),
      '#default_value' => $this->getSetting('title_length'),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 255,
    );
    $form['add_alias'] = array(
      '#type' => 'checkbox',
      '#disabled' => !$this->moduleHandler->moduleExists('path'),
      '#description' => $this->t('Requires path.module'),
      '#title' => $this->t('Add an url alias for each node.'),
      '#default_value' => FALSE,
    );
    $form['add_statistics'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Add statistics for each node (node_counter table).'),
      '#default_value' => TRUE,
      '#access' => $this->moduleHandler->moduleExists('statistics'),
    );

    $options = array();
    // We always need a language.
    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($languages as $langcode => $language) {
      $options[$langcode] = $language->getName();
    }

    $form['add_language'] = array(
      '#type' => 'select',
      '#title' => $this->t('Set language on nodes'),
      '#multiple' => TRUE,
      '#description' => $this->t('Requires locale.module'),
      '#options' => $options,
      '#default_value' => array(
        $this->languageManager->getDefaultLanguage()->getId(),
      ),
    );

    $form['#redirect'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  function settingsFormValidate(array $form, FormStateInterface $form_state) {
    if (!array_filter($form_state->getValue('node_types'))) {
      $form_state->setErrorByName('node_types', $this->t('Please select at least one content type'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateElements(array $values) {
    if ($values['num'] <= 50 && $values['max_comments'] <= 10) {
      $this->generateContent($values);
    }
    else {
      $this->generateBatchContent($values);
    }
  }

  /**
   * Method responsible for creating content when
   * the number of elements is less than 50.
   */
  private function generateContent($values) {
    $values['node_types'] = array_filter($values['node_types']);
    if (!empty($values['kill']) && $values['node_types']) {
      $this->contentKill($values);
    }

    if (!empty($values['node_types'])) {
      // Generate nodes.
      $this->develGenerateContentPreNode($values);
      $start = time();
      for ($i = 1; $i <= $values['num']; $i++) {
        $this->develGenerateContentAddNode($values);
        if ($this->isDrush8() && function_exists('drush_log') && $i % drush_get_option('feedback', 1000) == 0) {
          $now = time();
          drush_log(dt('Completed @feedback nodes (@rate nodes/min)', array('@feedback' => drush_get_option('feedback', 1000), '@rate' => (drush_get_option('feedback', 1000) * 60) / ($now - $start))), 'ok');
          $start = $now;
        }
      }
    }
    $this->setMessage($this->formatPlural($values['num'], '1 node created.', 'Finished creating @count nodes'));
  }

  /**
   * Method responsible for creating content when
   * the number of elements is greater than 50.
   */
  private function generateBatchContent($values) {
    // Setup the batch operations and save the variables.
    $operations[] = array('devel_generate_operation', array($this, 'batchContentPreNode', $values));

    // Add the kill operation.
    if ($values['kill']) {
      $operations[] = array('devel_generate_operation', array($this, 'batchContentKill', $values));
    }

    // Add the operations to create the nodes.
    for ($num = 0; $num < $values['num']; $num ++) {
      $operations[] = array('devel_generate_operation', array($this, 'batchContentAddNode', $values));
    }

    // Set the batch.
    $batch = array(
      'title' => $this->t('Generating Content'),
      'operations' => $operations,
      'finished' => 'devel_generate_batch_finished',
      'file' => drupal_get_path('module', 'devel_generate') . '/devel_generate.batch.inc',
    );
    batch_set($batch);
  }

  public function batchContentPreNode($vars, &$context) {
    $context['results'] = $vars;
    $context['results']['num'] = 0;
    $this->develGenerateContentPreNode($context['results']);
  }

  public function batchContentAddNode($vars, &$context) {
    $this->develGenerateContentAddNode($context['results']);
    $context['results']['num']++;
  }

  public function batchContentKill($vars, &$context) {
    $this->contentKill($context['results']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateDrushParams($args, $options = []) {
    $add_language = $this->isDrush8() ? drush_get_option('languages') : $options['languages'];
    if (!empty($add_language)) {
      $add_language = explode(',', str_replace(' ', '', $add_language));
      // Intersect with the enabled languages to make sure the language args
      // passed are actually enabled.
      $values['values']['add_language'] = array_intersect($add_language, array_keys($this->languageManager->getLanguages(LanguageInterface::STATE_ALL)));
    }

    $values['kill'] = $this->isDrush8() ? drush_get_option('kill') : $options['kill'];
    $values['title_length'] = 6;
    $values['num'] = array_shift($args);
    $values['max_comments'] = array_shift($args);
    $all_types = array_keys(node_type_get_names());
    $default_types = array_intersect(array('page', 'article'), $all_types);
    if ($this->isDrush8()) {
      $selected_types = _convert_csv_to_array(drush_get_option('types', $default_types));
    }
    else {
      $selected_types = StringUtils::csvToArray($options['types'] ?: $default_types);
    }

    if (empty($selected_types)) {
      throw new \Exception(dt('No content types available'));
    }

    $values['node_types'] = array_combine($selected_types, $selected_types);
    $node_types = array_filter($values['node_types']);

    if (!empty($values['kill']) && empty($node_types)) {
      throw new \Exception(dt('Please provide content type (--types) in which you want to delete the content.'));
    }

    // Checks for any missing content types before generating nodes.
    if (array_diff($node_types, $all_types)) {
      throw new \Exception(dt('One or more content types have been entered that don\'t exist on this site'));
    }

    return $values;
  }

  /**
   * Deletes all nodes of given node types.
   *
   * @param array $values
   *   The input values from the settings form.
   */
  protected function contentKill($values) {
    $nids = $this->nodeStorage->getQuery()
      ->condition('type', $values['node_types'], 'IN')
      ->execute();

    if (!empty($nids)) {
      $nodes = $this->nodeStorage->loadMultiple($nids);
      $this->nodeStorage->delete($nodes);
      $this->setMessage($this->t('Deleted %count nodes.', array('%count' => count($nids))));
    }
  }

  /**
   * Return the same array passed as parameter
   * but with an array of uids for the key 'users'.
   */
  protected function develGenerateContentPreNode(&$results) {
    // Get user id.
    $users = $this->getUsers();
    $results['users'] = $users;
  }

  /**
   * Create one node. Used by both batch and non-batch code branches.
   */
  protected function develGenerateContentAddNode(&$results) {
    if (!isset($results['time_range'])) {
      $results['time_range'] = 0;
    }
    $users = $results['users'];

    $node_type = array_rand(array_filter($results['node_types']));
    $uid = $users[array_rand($users)];

    $node = $this->nodeStorage->create(array(
      'nid' => NULL,
      'type' => $node_type,
      'title' => $this->getRandom()->sentences(mt_rand(1, $results['title_length']), TRUE),
      'uid' => $uid,
      'revision' => mt_rand(0, 1),
      'status' => TRUE,
      'promote' => mt_rand(0, 1),
      'created' => REQUEST_TIME - mt_rand(0, $results['time_range']),
      'langcode' => $this->getLangcode($results),
    ));

    // A flag to let hook_node_insert() implementations know that this is a
    // generated node.
    $node->devel_generate = $results;

    // Populate all fields with sample values.
    $this->populateFields($node);

    // See devel_generate_node_insert() for actions that happen before and after
    // this save.
    $node->save();
  }

  /**
   * Determine language based on $results.
   */
  protected function getLangcode($results) {
    if (isset($results['add_language'])) {
      $langcodes = $results['add_language'];
      $langcode = $langcodes[array_rand($langcodes)];
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    return $langcode;
  }

  /**
   * Retrieve 50 uids from the database.
   */
  protected function getUsers() {
    $users = array();
    $result = db_query_range("SELECT uid FROM {users}", 0, 50);
    foreach ($result as $record) {
      $users[] = $record->uid;
    }
    return $users;
  }

}
