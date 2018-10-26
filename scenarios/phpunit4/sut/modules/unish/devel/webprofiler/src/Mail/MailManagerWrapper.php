<?php

namespace Drupal\webprofiler\Mail;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\webprofiler\DataCollector\MailDataCollector;

/**
 * Class MailManagerWrapper
 */
class MailManagerWrapper extends DefaultPluginManager implements MailManagerInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\webprofiler\DataCollector\MailDataCollector
   */
  private $dataCollector;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  private $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * List of already instantiated mail plugins.
   *
   * @var array
   */
  protected $instances = array();

  /**
   * Constructs the MailManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   * @param \Drupal\webprofiler\DataCollector\MailDataCollector $dataCollector
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, TranslationInterface $string_translation, MailManagerInterface $mailManager, MailDataCollector $dataCollector) {
    parent::__construct('Plugin/Mail', $namespaces, $module_handler, 'Drupal\Core\Mail\MailInterface', 'Drupal\Core\Annotation\Mail');
    $this->alterInfo('mail_backend_info');
    $this->setCacheBackend($cache_backend, 'mail_backend_plugins');
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->stringTranslation = $string_translation;
    $this->dataCollector = $dataCollector;
    $this->mailManager = $mailManager;
  }

  /**
   * {@inheritdoc}
   */
  public function mail($module, $key, $to, $langcode, $params = array(), $reply = NULL, $send = TRUE) {
    $message = $this->mailManager->mail($module, $key, $to, $langcode, $params, $reply, $send);

    $instance = $this->mailManager->getInstance(['module' => $module, 'key' => $key]);
    $this->dataCollector->addMessage($message, $instance);

    return $message;
  }
}
