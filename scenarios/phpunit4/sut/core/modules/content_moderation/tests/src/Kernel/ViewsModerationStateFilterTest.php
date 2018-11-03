<?php

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\entity_test\Entity\EntityTestNoBundle;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests the views 'moderation_state_filter' filter plugin.
 *
 * @coversDefaultClass \Drupal\content_moderation\Plugin\views\filter\ModerationStateFilter
 *
 * @group content_moderation
 */
class ViewsModerationStateFilterTest extends ViewsKernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation_test_views',
    'node',
    'content_moderation',
    'workflows',
    'workflow_type_test',
    'entity_test',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp(FALSE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('entity_test_no_bundle');
    $this->installSchema('node', 'node_access');
    $this->installConfig('content_moderation_test_views');
    $this->installConfig('content_moderation');

    $node_type = NodeType::create([
      'type' => 'example',
    ]);
    $node_type->save();

    $node_type = NodeType::create([
      'type' => 'another_example',
    ]);
    $node_type->save();

    $node_type = NodeType::create([
      'type' => 'example_non_moderated',
    ]);
    $node_type->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests the content moderation state filter.
   */
  public function testStateFilterViewsRelationship() {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->getTypePlugin()->addState('translated_draft', 'Bar');
    $configuration = $workflow->getTypePlugin()->getConfiguration();
    $configuration['states']['translated_draft'] += [
      'published' => FALSE,
      'default_revision' => FALSE,
    ];
    $workflow->getTypePlugin()->setConfiguration($configuration);
    $workflow->save();

    // Create a published default revision and one forward draft revision.
    $node = Node::create([
      'type' => 'example',
      'title' => 'Test Node',
      'moderation_state' => 'published',
    ]);
    $node->save();
    $node->setNewRevision();
    $node->moderation_state = 'draft';
    $node->save();

    // Create a draft default revision.
    $second_node = Node::create([
      'type' => 'example',
      'title' => 'Second Node',
      'moderation_state' => 'draft',
    ]);
    $second_node->save();

    // Create a published default revision.
    $third_node = Node::create([
      'type' => 'example',
      'title' => 'Third node',
      'moderation_state' => 'published',
    ]);
    $third_node->save();

    // Add a non-moderated node.
    $fourth_node = Node::create([
      'type' => 'example_non_moderated',
      'title' => 'Fourth node',
    ]);
    $fourth_node->save();

    // Create a translated published revision.
    $translated_forward_revision = $third_node->addTranslation('fr');
    $translated_forward_revision->title = 'Translated Node';
    $translated_forward_revision->setNewRevision(TRUE);
    $translated_forward_revision->moderation_state = 'translated_draft';
    $translated_forward_revision->save();

    // The three default revisions are listed when no filter is specified.
    $this->assertNodesWithFilters([$node, $second_node, $third_node], []);

    // The default revision of node one and three are published.
    $this->assertNodesWithFilters([$node, $third_node], [
      'default_revision_state' => 'editorial-published',
    ]);

    // The default revision of node two is draft.
    $this->assertNodesWithFilters([$second_node], [
      'default_revision_state' => 'editorial-draft',
    ]);

    // Test the same three revisions on a view displaying content revisions.
    // Both nodes have one draft revision.
    $this->assertNodesWithFilters([$node, $second_node], [
      'moderation_state' => 'editorial-draft',
    ], 'test_content_moderation_state_filter_revision_table');
    // Creating a new forward revision of node three, creates a second published
    // revision of of the original language, hence there are two published
    // revisions of node three.
    $this->assertNodesWithFilters([$node, $third_node, $third_node], [
      'moderation_state' => 'editorial-published',
    ], 'test_content_moderation_state_filter_revision_table');
    // There is a single forward translated revision with a new state, which is
    // also filterable.
    $this->assertNodesWithFilters([$translated_forward_revision], [
      'moderation_state' => 'editorial-translated_draft',
    ], 'test_content_moderation_state_filter_revision_table');
  }

  /**
   * Test the moderation filter with a non-translatable entity type.
   */
  public function testNonTranslatableEntityType() {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_no_bundle', 'entity_test_no_bundle');
    $workflow->save();

    $test_entity = EntityTestNoBundle::create([
      'moderation_state' => 'draft',
    ]);
    $test_entity->save();

    $view = Views::getView('test_content_moderation_state_filter_entity_test');
    $view->setExposedInput([
      'moderation_state' => 'editorial-draft',
    ]);
    $view->execute();
    $this->assertIdenticalResultset($view, [['id' => $test_entity->id()]], ['id' => 'id']);
  }

  /**
   * Tests the list of states in the filter plugin.
   */
  public function testStateFilterStatesList() {
    // By default a view of nodes will not have states to filter.
    $this->assertPluginStates([]);

    // Adding a content type to the editorial workflow will enable all of the
    // editorial states.
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->save();
    $this->assertPluginStates([
      'Editorial' => [
        'editorial-draft' => 'Draft',
        'editorial-published' => 'Published',
        'editorial-archived' => 'Archived',
      ],
    ]);

    // Adding a workflow which is not content moderation will not add any
    // additional states to the views filter.
    $workflow = Workflow::create(['id' => 'test', 'type' => 'workflow_type_complex_test']);
    $workflow->getTypePlugin()->addState('draft', 'Draft');
    $workflow->save();
    $this->assertPluginStates([
      'Editorial' => [
        'editorial-draft' => 'Draft',
        'editorial-published' => 'Published',
        'editorial-archived' => 'Archived',
      ],
    ]);

    // Adding a new content moderation workflow will add additional states to
    // filter.
    $workflow = Workflow::create(['id' => 'moderation_test', 'type' => 'content_moderation', 'label' => 'Moderation test']);
    $workflow->getTypePlugin()->addState('foo', 'Foo State');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->save();
    $this->assertPluginStates([
      'Editorial' => [
        'editorial-draft' => 'Draft',
        'editorial-published' => 'Published',
        'editorial-archived' => 'Archived',
      ],
      'Moderation test' => [
        'moderation_test-foo' => 'Foo State',
        'moderation_test-draft' => 'Draft',
        'moderation_test-published' => 'Published',
      ],
    ]);

    // Deleting a workflow will remove the states from the filter.
    $workflow = Workflow::load('moderation_test');
    $workflow->delete();
    $this->assertPluginStates([
      'Editorial' => [
        'editorial-draft' => 'Draft',
        'editorial-published' => 'Published',
        'editorial-archived' => 'Archived',
      ],
    ]);

    // Deleting a state from a workflow will remove the state from the filter.
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->deleteState('archived');
    $workflow->save();
    $this->assertPluginStates([
      'Editorial' => [
        'editorial-draft' => 'Draft',
        'editorial-published' => 'Published',
      ],
    ]);
  }

  /**
   * Assert the plugin states.
   *
   * @param string[] $states
   *   The states which should appear in the filter.
   */
  protected function assertPluginStates($states) {
    $plugin = Views::pluginManager('filter')->createInstance('moderation_state_filter', []);
    $view = Views::getView('test_content_moderation_state_filter_base_table');
    $plugin->init($view, $view->getDisplay());
    $this->assertEquals($states, $plugin->getValueOptions());
  }

  /**
   * Assert the nodes appear when the test view is executed.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Nodes to assert are in the views result.
   * @param array $filters
   *   An array of filters to apply to the view.
   * @param string $view_id
   *   The view to execute for the results.
   */
  protected function assertNodesWithFilters(array $nodes, array $filters, $view_id = 'test_content_moderation_state_filter_base_table') {
    $view = Views::getView($view_id);
    $view->setExposedInput($filters);
    $view->execute();

    // Verify the join configuration.
    $query = $view->getQuery();
    $join = $query->getTableInfo('content_moderation_state')['join'];
    $configuration = $join->configuration;
    $this->assertEquals('content_moderation_state_field_revision', $configuration['table']);
    $this->assertEquals('content_entity_revision_id', $configuration['field']);
    $this->assertEquals('vid', $configuration['left_field']);
    $this->assertEquals('content_entity_type_id', $configuration['extra'][0]['field']);
    $this->assertEquals('node', $configuration['extra'][0]['value']);
    $this->assertEquals('langcode', $configuration['extra'][1]['field']);
    $this->assertEquals('langcode', $configuration['extra'][1]['left_field']);

    $expected_result = [];
    foreach ($nodes as $node) {
      $expected_result[] = ['nid' => $node->id()];
    }
    $this->assertIdenticalResultset($view, $expected_result, ['nid' => 'nid']);
  }

}
