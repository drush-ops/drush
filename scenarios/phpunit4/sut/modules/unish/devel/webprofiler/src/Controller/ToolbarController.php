<?php

namespace Drupal\webprofiler\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\webprofiler\Profiler\Profiler;
use Drupal\webprofiler\Profiler\TemplateManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Class ToolbarController
 */
class ToolbarController extends ControllerBase {

  /**
   * @var \Drupal\webprofiler\Profiler\Profiler
   */
  private $profiler;

  /**
   * @var \Drupal\webprofiler\Profiler\TemplateManager
   */
  private $templateManager;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('profiler'),
      $container->get('template_manager'),
      $container->get('renderer')
    );
  }

  /**
   * Constructs a new WebprofilerController.
   *
   * @param \Drupal\webprofiler\Profiler\Profiler $profiler
   * @param \Drupal\webprofiler\Profiler\TemplateManager $templateManager
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(Profiler $profiler, TemplateManager $templateManager, RendererInterface $renderer) {
    $this->profiler = $profiler;
    $this->templateManager = $templateManager;
    $this->renderer = $renderer;
  }

  /**
   * Generates the toolbar.
   *
   * @param Profile $profile
   *
   * @return array
   */
  public function toolbarAction(Profile $profile) {
    $this->profiler->disable();

    $templates = $this->templateManager->getTemplates($profile);

    $rendered = '';
    foreach ($templates as $name => $template) {
      $rendered .= $template->renderBlock('toolbar', [
        'collector' => $profile->getcollector($name),
        'token' => $profile->getToken(),
        'name' => $name
      ]);
    }

    $toolbar = [
      '#theme' => 'webprofiler_toolbar',
      '#toolbar' => $rendered,
      '#token' => $profile->getToken(),
    ];

    return new Response($this->renderer->render($toolbar));
  }

  /**
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function savePerformanceTimingAction(Profile $profile, Request $request) {
    $this->profiler->disable();

    $data = Json::decode($request->getContent());

    /** @var  $collector */
    $collector = $profile->getCollector('performance_timing');
    $collector->setData($data);
    $this->profiler->updateProfile($profile);

    return new JsonResponse(['success' => TRUE]);
  }
}
