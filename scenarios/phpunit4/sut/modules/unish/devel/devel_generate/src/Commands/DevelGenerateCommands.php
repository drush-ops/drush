<?php
namespace Drupal\devel_generate\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\devel_generate\DevelGenerateBaseInterface;
use Drush\Commands\DrushCommands;

/**
 * For commands that are parts of modules, Drush expects to find commandfiles in
 * __MODULE__/src/Commands, and the namespace is Drupal/__MODULE__/Commands.
 *
 * In addition to a commandfile like this one, you need to add a drush.services.yml
 * in root of your module like this module does.
 */
class DevelGenerateCommands extends DrushCommands {

  /**
   * @var DevelGenerateBaseInterface $manager
   */
  protected $manager;

  /**
   * The plugin instance.
   *
   * @var DevelGenerateBaseInterface $instance
   */
  protected $pluginInstance;

  /**
   * The Generate plugin parameters.
   *
   * @var array $parameters
   */
  protected $parameters;

  /**
   * DevelGenerateCommands constructor.
   * @param $manager
   */
  public function __construct($manager) {
    parent::__construct();
    $this->setManager($manager);
  }

  /**
   * @return \Drupal\devel_generate\DevelGenerateBaseInterface
   */
  public function getManager() {
    return $this->manager;
  }

  /**
   * @param \Drupal\devel_generate\DevelGenerateBaseInterface $manager
   */
  public function setManager($manager) {
    $this->manager = $manager;
  }

  /**
   * @return mixed
   */
  public function getPluginInstance() {
    return $this->pluginInstance;
  }

  /**
   * @param mixed $pluginInstance
   */
  public function setPluginInstance($pluginInstance) {
    $this->pluginInstance = $pluginInstance;
  }

  /**
   * @return array
   */
  public function getParameters() {
    return $this->parameters;
  }

  /**
   * @param array $parameters
   */
  public function setParameters($parameters) {
    $this->parameters = $parameters;
  }

  /**
   * Create users.
   *
   * @command devel-generate-users
   * @pluginId user
   * @param $num Number of users to generate.
   * @option kill Delete all users before generating new ones.
   * @option roles A comma delimited list of role IDs for new users. Don't specify 'authenticated'.
   * @option pass Specify a password to be set for all generated users.
   * @aliases genu
   */
  public function users($num = 50, $options = ['kill' => FALSE, 'roles' => '']) {
    // @todo pass $options to the plugins.
    $this->generate();
  }

  /**
   * Create terms in specified vocabulary.
   *
   * @command devel-generate-terms
   * @pluginId term
   * @param $machine_name Vocabulary machine name into which new terms will be inserted.
   * @param $num Number of terms to generate.
   * @option kill Delete all terms before generating new ones.
   * @option feedback An integer representing interval for insertion rate logging.
   * @validate-entity-load taxonomy_vocabulary machine_name
   * @aliases gent
   */
  public function terms($machine_name, $num = 50, $options = ['feedback' => 1000]) {
    $this->generate();
  }

  /**
   * Create vocabularies.
   *
   * @command devel-generate-vocabs
   * @pluginId vocabulary
   * @param $num Number of vocabularies to generate.
   * @option kill Delete all vocabs before generating new ones.
   * @aliases genv
   * @validate-module-enabled taxonomy
   */
  public function vocabs($num = 1, $options = ['kill' => FALSE]) {
    $this->generate();
  }

  /**
   * Create menus.
   *
   * @command devel-generate-menus
   * @pluginId menu
   * @param $number_menus Number of menus to generate.
   * @param $number_links Number of links to generate.
   * @param $max_depth Max link depth.
   * @param $max_width Max width of first level of links.
   * @option kill Delete all content before generating new content.
   * @aliases genm
   * @validate-module-enabled menu_link_content
   */
  public function menus($number_menus = 2, $number_links = 50, $max_depth = 3, $max_width = 8, $options = ['kill' => FALSE]) {
    $this->generate();
  }

  /**
   * Create content.
   *
   * @command devel-generate-content
   * @pluginId content
   * @param $num Number of nodes to generate.
   * @param $max_comments Maximum number of comments to generate.
   * @option kill Delete all content before generating new content.
   * @option types A comma delimited list of content types to create. Defaults to page,article.
   * @option feedback An integer representing interval for insertion rate logging.
   * @option skip-fields A comma delimited list of fields to omit when generating random values
   * @option languages A comma-separated list of language codes
   * @aliases genc
   * @validate-module-enabled node
   */
  public function content($num = 50, $max_comments = 0, $options = ['kill' => FALSE, 'types' => 'page,article', 'feedback' => 1000]) {
    $this->generate();
    drush_backend_batch_process();
  }


  /**
   * @hook validate
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validate(CommandData $commandData) {
    $manager = $this->getManager();
    $args = $commandData->input()->getArguments();
    $commandName = array_shift($args);
    /** @var DevelGenerateBaseInterface $instance */
    $instance = $manager->createInstance($commandData->annotationData()->get('pluginId'), array());
    $this->setPluginInstance($instance);
    $parameters = $instance->validateDrushParams($args, $commandData->input()->getOptions());
    $this->setParameters($parameters);
  }

  public function generate() {
    $instance = $this->getPluginInstance();
    $instance->generate($this->getParameters());
  }
}
