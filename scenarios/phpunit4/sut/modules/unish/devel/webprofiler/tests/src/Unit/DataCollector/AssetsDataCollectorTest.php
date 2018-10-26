<?php

namespace Drupal\Tests\webprofiler\Unit\DataCollector;

use Drupal\webprofiler\Asset\CssCollectionRendererWrapper;
use Drupal\webprofiler\DataCollector\AssetsDataCollector;

/**
 * @coversDefaultClass \Drupal\webprofiler\DataCollector\AssetsDataCollector
 *
 * @group webprofiler
 */
class AssetsDataCollectorTest extends DataCollectorBaseTest {

  /**
   * @var \Drupal\webprofiler\DataCollector\AssetsDataCollector
   */
  private $assetDataCollector;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  private $assetCollectionRendererInterface;

  const ROOT = 'test_root';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->assetDataCollector = new AssetsDataCollector(AssetsDataCollectorTest::ROOT);
    $this->assetCollectionRendererInterface = $this->getMock('Drupal\Core\Asset\AssetCollectionRendererInterface');
  }

  /**
   * Tests the Assets data collector.
   */
  public function testCSS() {
    $css = [
      'core/assets/vendor/normalize-css/normalize.css' => [
        'weight' => -219.944,
        'group' => 0,
        'type' => 'file',
        'data' => 'core\/assets\/vendor\/normalize-css\/normalize.css',
        'version' => '3.0.3',
        'media' => 'all',
        'preprocess' => TRUE,
        'browsers' => [
          'IE' => TRUE,
          '!IE' => TRUE,
        ],
      ],
    ];

    $cssCollectionRendererWrapper = new CssCollectionRendererWrapper($this->assetCollectionRendererInterface, $this->assetDataCollector);
    $cssCollectionRendererWrapper->render($css);

    $this->assertEquals(1, $this->assetDataCollector->getCssCount());

    $this->assetDataCollector->collect($this->request, $this->response, $this->exception);

    $data = $this->assetDataCollector->getData();
    $this->assertEquals(AssetsDataCollectorTest::ROOT . '/', $data['assets']['installation_path']);
  }

}
