<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\Theme\ThemeNegotiatorWrapper;
use Drupal\webprofiler\Twig\Dumper\HtmlDumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Class ThemeDataCollector
 */
class ThemeDataCollector extends DataCollector implements DrupalDataCollectorInterface, LateDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  private $themeManager;

  /**
   * @var \Drupal\Core\Theme\ThemeNegotiatorInterface
   */
  private $themeNegotiator;

  /**
   * @var \Twig_Profiler_Profile
   */
  private $profile;

  /**
   * @var
   */
  private $computed;

  /**
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   * @param \Drupal\Core\Theme\ThemeNegotiatorInterface $themeNegotiator
   * @param \Twig_Profiler_Profile $profile
   */
  public function __construct(ThemeManagerInterface $themeManager, ThemeNegotiatorInterface $themeNegotiator, \Twig_Profiler_Profile $profile) {
    $this->themeManager = $themeManager;
    $this->themeNegotiator = $themeNegotiator;
    $this->profile = $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $activeTheme = $this->themeManager->getActiveTheme();

    $this->data['activeTheme'] = [
      'name' => $activeTheme->getName(),
      'path' => $activeTheme->getPath(),
      'engine' => $activeTheme->getEngine(),
      'owner' => $activeTheme->getOwner(),
      'baseThemes' => $activeTheme->getBaseThemes(),
      'extension' => $activeTheme->getExtension(),
      'styleSheetsRemove' => $activeTheme->getStyleSheetsRemove(),
      'libraries' => $activeTheme->getLibraries(),
      'regions' => $activeTheme->getRegions(),
    ];

    if ($this->themeNegotiator instanceof ThemeNegotiatorWrapper) {
      $this->data['negotiator'] = [
        'class' => $this->getMethodData($this->themeNegotiator->getNegotiator(), 'determineActiveTheme'),
        'id' => $this->themeNegotiator->getNegotiator()->_serviceId,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lateCollect() {
    $this->data['twig'] = serialize($this->profile);
  }

  /**
   * @return string
   */
  public function getActiveTheme() {
    return $this->data['activeTheme'];
  }

  /**
   * @return array
   */
  public function getThemeNegotiator() {
    return $this->data['negotiator'];
  }

  /**
   * @return int
   */
  public function getTime() {
    return $this->getProfile()->getDuration() * 1000;
  }

  /**
   * @return mixed
   */
  public function getTemplateCount() {
    return $this->getComputedData('template_count');
  }

  /**
   * @return mixed
   */
  public function getTemplates() {
    return $this->getComputedData('templates');
  }

  /**
   * @return mixed
   */
  public function getBlockCount() {
    return $this->getComputedData('block_count');
  }

  /**
   * @return mixed
   */
  public function getMacroCount() {
    return $this->getComputedData('macro_count');
  }

  /**
   * @return \Twig_Markup
   */
  public function getHtmlCallGraph() {
    $dumper = new HtmlDumper();

    return new \Twig_Markup($dumper->dump($this->getProfile()), 'UTF-8');
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'theme';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Theme');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Name: @name', ['@name' => $this->getActiveTheme()['name']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAlVJREFUeNrMlk1IVFEUx53K6AOjUBQri6SihQiRziaRQegDlKJFBCpSiZtoE4hWtFFRwcCVBqEWfaAE7SoXajXLQoRcFAUSaBgUJQxKYUrT78D/wevx3psaE7rw49x377ln7vl4500kmUxm/OuxJmMVxrqwzVgsth5xHKogCnthCd7BU7gdj8envOciQe5j8CyiFQq0tAgDsA+OaG0eLmF4INQoxrYg+iEXhqEGiqGBw/3SGUeU6MgC61mBMUU5GzEKJ6ES5S5knbbXui/jmv8MjCkGMxEPFbsFyAeL1y6pXEenSO4fctlIhCXqqtmGj5ADcblZoX1z8aLn/Es475soDhcyfwt3LPDK8jPYFlIcZrCCEH0LqlP7NXN/CCUL/CvmkynKcczPoNv9Y5LXuHVC2Y6mMFoetOG4/0kl9LfDquMyN076ue/I11ANHSqVH0pgLUz7GK2HjUHuf1DGm/jVYZVYJeILz5163oy46Tn/GL5r396yc+hXOzcckiyVQp7CsVNvmI2DHoODcMFcR6eZ+RO4747pJuYjcFilssMM6vB7+Or8IGMWWjDWx7ntzHvglCWZtfbf3n0UtiJ6FVO/8QbuQh+H59C37nUD9sAj1k6ENZQylctuJWsGJswDDibY32C3gitKsCUwyt7nlK0vpMc2Wh/Q4zIcxeDztDu/QjSoPmujzWswZef3GLRXuV2xPa3u/yDwjfoDgxE1nP1aquOG91b64StWlp0xtaKvKbfMUS1maukWvEj7a6pxBg6oL1iddnsbSFox/S/+TKyK0V8CDABrCdI/1oTqiQAAAABJRU5ErkJggg==';
  }

  /**
   * @return array
   */
  public function getData() {
    $data = $this->data;

    $data['twig'] = [
      'callgraph' => (string) $this->getHtmlCallGraph(),
      'render_time' => $this->getTime(),
      'template_count' => $this->getTemplateCount(),
      'templates' => $this->getTemplates(),
      'block_count' => $this->getBlockCount(),
      'macro_count' => $this->getMacroCount(),
    ];

    return $data;
  }

  /**
   * @return mixed|\Twig_Profiler_Profile
   */
  private function getProfile() {
    if (NULL === $this->profile) {
      $this->profile = unserialize($this->data['twig']);
    }

    return $this->profile;
  }

  /**
   * @param $index
   *
   * @return mixed
   */
  private function getComputedData($index) {
    if (NULL === $this->computed) {
      $this->computed = $this->computeData($this->getProfile());
    }

    return $this->computed[$index];
  }

  /**
   * @param \Twig_Profiler_Profile $profile
   *
   * @return array
   */
  private function computeData(\Twig_Profiler_Profile $profile) {
    $data = [
      'template_count' => 0,
      'block_count' => 0,
      'macro_count' => 0,
    ];

    $templates = [];
    foreach ($profile as $p) {
      $d = $this->computeData($p);

      $data['template_count'] += ($p->isTemplate() ? 1 : 0) + $d['template_count'];
      $data['block_count'] += ($p->isBlock() ? 1 : 0) + $d['block_count'];
      $data['macro_count'] += ($p->isMacro() ? 1 : 0) + $d['macro_count'];

      if ($p->isTemplate()) {
        if (!isset($templates[$p->getTemplate()])) {
          $templates[$p->getTemplate()] = 1;
        }
        else {
          $templates[$p->getTemplate()]++;
        }
      }

      foreach ($d['templates'] as $template => $count) {
        if (!isset($templates[$template])) {
          $templates[$template] = $count;
        }
        else {
          $templates[$template] += $count;
        }
      }
    }
    $data['templates'] = $templates;

    return $data;
  }
}
