<?php

namespace Drupal\Tests\block\Unit;

use Drupal\block\BlockForm;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\block\BlockForm
 * @group block
 */
class BlockFormTest extends UnitTestCase {

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $conditionManager;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $language;


  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $themeHandler;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The mocked context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $contextRepository;

  /**
   * The plugin form manager.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $pluginFormFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->conditionManager = $this->getMock('Drupal\Core\Executable\ExecutableManagerInterface');
    $this->language = $this->getMock('Drupal\Core\Language\LanguageManagerInterface');
    $this->contextRepository = $this->getMock('Drupal\Core\Plugin\Context\ContextRepositoryInterface');

    $this->entityManager = $this->getMock('Drupal\Core\Entity\EntityManagerInterface');
    $this->storage = $this->getMock('Drupal\Core\Config\Entity\ConfigEntityStorageInterface');
    $this->themeHandler = $this->getMock('Drupal\Core\Extension\ThemeHandlerInterface');
    $this->entityManager->expects($this->any())
      ->method('getStorage')
      ->will($this->returnValue($this->storage));

    $this->pluginFormFactory = $this->prophesize(PluginFormFactoryInterface::class);
  }

  /**
   * Mocks a block with a block plugin.
   *
   * @param string $machine_name
   *   The machine name of the block plugin.
   *
   * @return \Drupal\block\BlockInterface|\PHPUnit_Framework_MockObject_MockObject
   *   The mocked block.
   */
  protected function getBlockMockWithMachineName($machine_name) {
    $plugin = $this->getMockBuilder(BlockBase::class)
      ->disableOriginalConstructor()
      ->getMock();
    $plugin->expects($this->any())
      ->method('getMachineNameSuggestion')
      ->will($this->returnValue($machine_name));

    $block = $this->getMockBuilder(Block::class)
      ->disableOriginalConstructor()
      ->getMock();
    $block->expects($this->any())
      ->method('getPlugin')
      ->will($this->returnValue($plugin));
    return $block;
  }

  /**
   * Tests the unique machine name generator.
   *
   * @see \Drupal\block\BlockForm::getUniqueMachineName()
   */
  public function testGetUniqueMachineName() {
    $blocks = [];

    $blocks['test'] = $this->getBlockMockWithMachineName('test');
    $blocks['other_test'] = $this->getBlockMockWithMachineName('other_test');
    $blocks['other_test_1'] = $this->getBlockMockWithMachineName('other_test');
    $blocks['other_test_2'] = $this->getBlockMockWithMachineName('other_test');

    $query = $this->getMock('Drupal\Core\Entity\Query\QueryInterface');
    $query->expects($this->exactly(5))
      ->method('condition')
      ->will($this->returnValue($query));

    $query->expects($this->exactly(5))
      ->method('execute')
      ->will($this->returnValue(['test', 'other_test', 'other_test_1', 'other_test_2']));

    $this->storage->expects($this->exactly(5))
      ->method('getQuery')
      ->will($this->returnValue($query));

    $block_form_controller = new BlockForm($this->entityManager, $this->conditionManager, $this->contextRepository, $this->language, $this->themeHandler, $this->pluginFormFactory->reveal());

    // Ensure that the block with just one other instance gets the next available
    // name suggestion.
    $this->assertEquals('test_2', $block_form_controller->getUniqueMachineName($blocks['test']));

    // Ensure that the block with already three instances (_0, _1, _2) gets the
    // 4th available name.
    $this->assertEquals('other_test_3', $block_form_controller->getUniqueMachineName($blocks['other_test']));
    $this->assertEquals('other_test_3', $block_form_controller->getUniqueMachineName($blocks['other_test_1']));
    $this->assertEquals('other_test_3', $block_form_controller->getUniqueMachineName($blocks['other_test_2']));

    // Ensure that a block without an instance yet gets the suggestion as
    // unique machine name.
    $last_block = $this->getBlockMockWithMachineName('last_test');
    $this->assertEquals('last_test', $block_form_controller->getUniqueMachineName($last_block));
  }

}
