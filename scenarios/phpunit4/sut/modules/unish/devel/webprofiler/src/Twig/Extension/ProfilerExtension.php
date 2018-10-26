<?php

namespace Drupal\webprofiler\Twig\Extension;

use Drupal\webprofiler\Helper\ClassShortenerInterface;
use Drupal\webprofiler\Helper\IdeLinkGeneratorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Class ProfilerExtension
 */
class ProfilerExtension extends \Twig_Extension_Profiler {

  /**
   * @var \Symfony\Component\Stopwatch\Stopwatch
   */
  private $stopwatch;
  private $events;
  private $ideLink;
  private $classShortener;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Twig_Profiler_Profile $profile, Stopwatch $stopwatch = NULL, IdeLinkGeneratorInterface $ideLink, ClassShortenerInterface $classShortener) {
    parent::__construct($profile);

    $this->ideLink = $ideLink;
    $this->classShortener = $classShortener;

    $this->stopwatch = $stopwatch;
    $this->events = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function enter(\Twig_Profiler_Profile $profile) {
    if ($this->stopwatch && $profile->isTemplate()) {
      $this->events[$profile] = $this->stopwatch->start($profile->getName(), 'template');
    }

    parent::enter($profile);
  }

  /**
   * {@inheritdoc}
   */
  public function leave(\Twig_Profiler_Profile $profile) {
    parent::leave($profile);

    if ($this->stopwatch && $profile->isTemplate()) {
      $this->events[$profile]->stop();
      unset($this->events[$profile]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('abbr', [$this, 'getAbbr']),
      new \Twig_SimpleFunction('idelink', [$this, 'getIdeLink']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'native_profiler';
  }

  /**
   * @param $class
   *
   * @return string
   */
  public function getAbbr($class) {
    return $this->classShortener->shortenClass($class);
  }

  /**
   * @param $file
   * @param $line
   *
   * @return string
   */
  public function getIdeLink($file, $line) {
    return $this->ideLink->generateLink($file, $line);
  }
}
