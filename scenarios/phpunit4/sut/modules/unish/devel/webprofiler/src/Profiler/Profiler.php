<?php

namespace Drupal\webprofiler\Profiler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler as SymfonyProfiler;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;

/**
 * Class Profiler
 */
class Profiler extends SymfonyProfiler {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface $config
   */
  private $config;

  /**
   * @var array
   */
  private $activeToolbarItems;

  private $localStorage;
  private $localLogger;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface $storage
   *   A ProfilerStorageInterface instance
   * @param \Psr\Log\LoggerInterface $logger
   *   A LoggerInterface instance
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   */
  public function __construct(ProfilerStorageInterface $storage, LoggerInterface $logger = NULL, ConfigFactoryInterface $config) {
    parent::__construct($storage, $logger);

    $this->localStorage = $storage;
    $this->localLogger = $logger;

    $this->config = $config;
    $this->activeToolbarItems = $this->config->get('webprofiler.config')
      ->get('active_toolbar_items');
  }

  /**
   * {@inheritdoc}
   */
  public function add(DataCollectorInterface $collector) {
    // drupal collector should not be disabled
    if ($collector->getName() == 'drupal') {
      parent::add($collector);
    }
    else {
      if ($this->activeToolbarItems && array_key_exists($collector->getName(), $this->activeToolbarItems) && $this->activeToolbarItems[$collector->getName()] !== '0') {
        parent::add($collector);
      }
    }
  }

  /**
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
   *
   * @return bool
   */
  public function updateProfile(Profile $profile) {
    if (!($ret = $this->localStorage->write($profile)) && NULL !== $this->localLogger) {
      $this->localLogger->warning('Unable to store the profiler information.');
    }

    return $ret;
  }
}
