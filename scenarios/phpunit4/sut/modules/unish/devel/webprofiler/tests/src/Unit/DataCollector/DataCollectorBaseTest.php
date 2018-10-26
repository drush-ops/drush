<?php

namespace Drupal\Tests\webprofiler\Unit\DataCollector;

use Drupal\Tests\UnitTestCase;

/**
 * Class DataCollectorBaseTest.
 *
 * @group webprofiler
 */
abstract class DataCollectorBaseTest extends UnitTestCase {

  /**
   * @var
   */
  protected $request;

  /**
   * @var
   */
  protected $response;

  /**
   * @var
   */
  protected $exception;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->request = $this->getMock('Symfony\Component\HttpFoundation\Request');
    $this->response = $this->getMock('Symfony\Component\HttpFoundation\Response');
    $this->exception = $this->getMock('Exception');
  }

}
