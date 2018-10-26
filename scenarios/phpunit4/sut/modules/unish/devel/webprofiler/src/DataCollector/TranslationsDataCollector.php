<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\webprofiler\StringTranslation\TranslationManagerWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class TranslationsDataCollector
 */
class TranslationsDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  private $translation;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  private $urlGenerator;

  /**
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   */
  public function __construct(TranslationInterface $translation, UrlGeneratorInterface $urlGenerator) {
    $this->translation = $translation;
    $this->urlGenerator = $urlGenerator;

    $this->data['translations']['translated'] = [];
    $this->data['translations']['untranslated'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    if($this->translation instanceof TranslationManagerWrapper) {
      /** \Drupal\webprofiler\StringTranslation\TranslationManagerWrapper $this->translation */
      $this->data['translations']['translated'] = $this->translation->getTranslated();
      $this->data['translations']['untranslated'] = $this->translation->getUntranslated();
    }
    $this->data['user_interface_translations_path'] = $this->urlGenerator->generateFromRoute('locale.translate_page');
  }

  /**
   * @return int
   */
  public function getTranslatedCount() {
    return count($this->data['translations']['translated']);
  }

  /**
   * @return int
   */
  public function getUntranslatedCount() {
    return count($this->data['translations']['untranslated']);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'translations';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Translations');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Translated: @translated, untranslated: @untranslated', [
      '@translated' => $this->getTranslatedCount(),
      '@untranslated' => $this->getUntranslatedCount()
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAgpJREFUeNrUVrFuwjAQPUcZiAQDQ6WEbgwwMCJVqhjgBxhYGSvEDzBkZYSBH+ADoFJHlg4MdIMKxiLBXKAbUmEm9bPiyAREEgRDTzqZ2Mfzu7tnJ8xxHLq1aXQHuwsos2078p9arVYg0yL3CXcnwCdubKDp3F+5myFin9xYK0xNzQiZm1c3KpVKUb1ep2QyebvuFwoFSqfTlM/nbweay+VoOBxeDar7JwC03W49UO5fs9nsZTwef8qYUqkUjSmAYrGYqKlhGFSpVH45oBFFdkdM0RjUst1uC7Z45ofjWdO0t8Ph8CDjyuUyZTIZ6na7tNvtTmSn+2s5n88FIMwdPzhgUWYBA2A2mxVjIpEQarEsiwaDAS2XSxPH9OI1xVNnbmo0Go28eTDkAOL3YrGg/X4v1tfr9WmjLplsULVaFawbjYbHkjfzqPsr7o9RwJE2HOkifbAGS1ljdL/G/ScsWK/XE+NmsxGMkW6/3xfgWMMIpu9hLgkEN5tNDwClgAoADjAYRszr8m7kD2gGU5uh1hEjmoGUUU/oGLVEXaVhA2yoKYAwRwFx/Ezj8bjHEIYaSgVgDptNp1NiePG5QCfS4qyZXANop9MR7JANWAFA6hWgWEcmqqTYOWBVl0jZf0ViU+hUNk0AyVf0ObYK0+8IslupF8qlkxVWdoipsaCPiaBr7uwr+t98ofwJMADuIP9IDjFbLwAAAABJRU5ErkJggg==';
  }
}
