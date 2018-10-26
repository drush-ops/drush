<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drupal\webprofiler\Profiler;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler as SymfonyProfiler;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Profiler Templates Manager
 */
class TemplateManager {

  /**
   * @var \Twig_Environment
   */
  protected $twig;

  /**
   * @var \Twig_Loader_Chain
   */
  protected $twigLoader;

  /**
   * @var array
   */
  protected $templates;

  /**
   * @var \Symfony\Component\HttpKernel\Profiler\Profiler
   */
  protected $profiler;

  /**
   * Constructor.
   *
   * @param SymfonyProfiler $profiler
   * @param \Twig_Environment $twig
   * @param \Twig_Loader_Chain $twigLoader
   * @param array $templates
   */
  public function __construct(SymfonyProfiler $profiler, \Twig_Environment $twig, \Twig_Loader_Chain $twigLoader, array $templates) {
    $this->profiler = $profiler;
    $this->twig = $twig;
    $this->twigLoader = $twigLoader;
    $this->templates = $templates;
  }

  /**
   * Gets the template name for a given panel.
   *
   * @param Profile $profile
   * @param string $panel
   *
   * @return mixed
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function getName(Profile $profile, $panel) {
    $templates = $this->getNames($profile);

    if (!isset($templates[$panel])) {
      throw new NotFoundHttpException(sprintf('Panel "%s" is not registered in profiler or is not present in viewed profile.', $panel));
    }

    return $templates[$panel];
  }

  /**
   * Gets the templates for a given profile.
   *
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
   *
   * @return array
   */
  public function getTemplates(Profile $profile) {
    $templates = $this->getNames($profile);
    foreach ($templates as $name => $template) {
      $templates[$name] = $this->twig->loadTemplate($template);
    }

    return $templates;
  }

  /**
   * Gets template names of templates that are present in the viewed profile.
   *
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
   *
   * @return array
   *
   * @throws \UnexpectedValueException
   */
  protected function getNames(Profile $profile) {
    $templates = [];

    foreach ($this->templates as $arguments) {
      if (NULL === $arguments) {
        continue;
      }

      list($name, $template) = $arguments;

      if (!$this->profiler->has($name) || !$profile->hasCollector($name)) {
        continue;
      }

      if ('.html.twig' === substr($template, -10)) {
        $template = substr($template, 0, -10);
      }

      if (!$this->twigLoader->exists($template . '.html.twig')) {
        throw new \UnexpectedValueException(sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
      }

      $templates[$name] = $template . '.html.twig';
    }

    return $templates;
  }
}
