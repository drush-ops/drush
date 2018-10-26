<?php

namespace Drupal\webprofiler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Url;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\webprofiler\Profiler\ProfilerStorageManager;
use Drupal\webprofiler\Profiler\TemplateManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\webprofiler\Profiler\Profiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class DashboardController
 */
class DashboardController extends ControllerBase {

  /**
   * @var \Drupal\webprofiler\Profiler\Profiler
   */
  private $profiler;

  /**
   * @var \Symfony\Cmf\Component\Routing\ChainRouter
   */
  private $router;

  /**
   * @var \Drupal\webprofiler\Profiler\TemplateManager
   */
  private $templateManager;

  /**
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  private $date;

  /**
   * @var \Drupal\webprofiler\Profiler\ProfilerStorageManager
   */
  private $storageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('profiler'),
      $container->get('router'),
      $container->get('template_manager'),
      $container->get('date.formatter'),
      $container->get('profiler.storage_manager')
    );
  }

  /**
   * Constructs a new WebprofilerController.
   *
   * @param \Drupal\webprofiler\Profiler\Profiler $profiler
   * @param \Symfony\Component\Routing\RouterInterface $router
   * @param \Drupal\webprofiler\Profiler\TemplateManager $templateManager
   * @param \Drupal\Core\Datetime\DateFormatter $date
   * @param \Drupal\webprofiler\Profiler\ProfilerStorageManager $storageManager
   */
  public function __construct(Profiler $profiler, RouterInterface $router, TemplateManager $templateManager, DateFormatter $date, ProfilerStorageManager $storageManager) {
    $this->profiler = $profiler;
    $this->router = $router;
    $this->templateManager = $templateManager;
    $this->date = $date;
    $this->storageManager = $storageManager;
  }

  /**
   * Generates the dashboard page.
   *
   * @param Profile $profile
   *
   * @return array
   */
  public function dashboardAction(Profile $profile) {
    $this->profiler->disable();

    $templateManager = $this->templateManager;
    $templates = $templateManager->getTemplates($profile);

    $panels = [];
    $libraries = ['webprofiler/dashboard'];
    $drupalSettings = [
      'webprofiler' => [
        'token' => $profile->getToken(),
        'ide_link' => $this->config('webprofiler.config')->get('ide_link'),
        'ide_link_remote' => $this->config('webprofiler.config')->get('ide_link_remote'),
        'ide_link_local' => $this->config('webprofiler.config')->get('ide_link_local'),
        'collectors' => [],
      ],
    ];

    foreach ($templates as $name => $template) {
      /** @var DrupalDataCollectorInterface $collector */
      $collector = $profile->getCollector($name);

      if ($collector->hasPanel()) {
        $rendered = $template->renderBlock('panel', [
          'token' => $profile->getToken(),
          'name' => $name,
        ]);

        $panels[] = [
          '#theme' => 'webprofiler_panel',
          '#panel' => $rendered,
        ];

        $drupalSettings['webprofiler']['collectors'][] = [
          'id' => $name,
          'name' => $name,
          'label' => $collector->getTitle(),
          'summary' => $collector->getPanelSummary(),
          'icon' => $collector->getIcon(),
        ];

        $libraries = array_merge($libraries, $collector->getLibraries());
        $drupalSettings['webprofiler'] += $collector->getDrupalSettings();
      }
    }

    $build = [];
    $build['panels'] = [
      '#theme' => 'webprofiler_dashboard',
      '#profile' => $profile,
      '#panels' => $panels,
      '#spinner_path' => '/' . $this->moduleHandler()
          ->getModule('webprofiler')
          ->getPath() . '/images/searching.gif',
      '#attached' => [
        'drupalSettings' => $drupalSettings,
        'library' => $libraries,
      ],
    ];

    return $build;
  }

  /**
   * Generates the list page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   */
  public function listAction(Request $request) {
    $limit = $request->get('limit', 10);
    $this->profiler->disable();

    $ip = $request->query->get('ip');
    $method = $request->query->get('method');
    $url = $request->query->get('url');

    $profiles = $this->profiler->find($ip, $url, $limit, $method, '', '');

    $rows = [];
    if (count($profiles)) {
      foreach ($profiles as $profile) {
        $row = [];
        $row[] = $this->l($profile['token'], new Url('webprofiler.dashboard', ['profile' => $profile['token']]));
        $row[] = $profile['ip'];
        $row[] = $profile['method'];
        $row[] = $profile['url'];
        $row[] = $this->date->format($profile['time']);

        $rows[] = $row;
      }
    }
    else {
      $rows[] = [
        [
          'data' => $this->t('No profiles found'),
          'colspan' => 6,
        ],
      ];
    }

    $build = [];

    $storage_id = $this->config('webprofiler.config')->get('storage');
    $storage = $this->storageManager->getStorage($storage_id);

    $build['resume'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ message }}</p>',
      '#context' => [
        'message' => $this->t('Profiles stored with %storage service.', ['%storage' => $storage['title']]),
      ],
    ];

    $build['filters'] = $this->formBuilder()
      ->getForm('Drupal\\webprofiler\\Form\\ProfilesFilterForm');

    $build['table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#header' => [
        $this->t('Token'),
        [
          'data' => $this->t('Ip'),
          'class' => [RESPONSIVE_PRIORITY_LOW],
        ],
        [
          'data' => $this->t('Method'),
          'class' => [RESPONSIVE_PRIORITY_LOW],
        ],
        $this->t('Url'),
        [
          'data' => $this->t('Time'),
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
      ],
      '#sticky' => TRUE,
    ];

    return $build;
  }

  /**
   * Exposes collector's data as JSON.
   *
   * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
   * @param $collector
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function restCollectorAction(Profile $profile, $collector) {
    $this->profiler->disable();

    $data = $profile->getCollector($collector)->getData();

    return new JsonResponse(['data' => $data]);
  }
}
