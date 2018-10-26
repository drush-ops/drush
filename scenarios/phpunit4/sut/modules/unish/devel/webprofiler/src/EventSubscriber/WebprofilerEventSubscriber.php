<?php

namespace Drupal\webprofiler\EventSubscriber;

use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class WebprofilerEventSubscriber
 */
class WebprofilerEventSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(AccountInterface $currentUser, UrlGeneratorInterface $urlGenerator, RendererInterface $renderer) {
    $this->currentUser = $currentUser;
    $this->urlGenerator = $urlGenerator;
    $this->renderer = $renderer;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   */
  public function onKernelResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();
    $request = $event->getRequest();

    if ($response->headers->has('X-Debug-Token') && NULL !== $this->urlGenerator) {
      $response->headers->set(
        'X-Debug-Token-Link',
        $this->urlGenerator->generate('webprofiler.dashboard', ['profile' => $response->headers->get('X-Debug-Token')])
      );
    }

    // do not capture redirects or modify XML HTTP Requests
    if ($request->isXmlHttpRequest()) {
      return;
    }

    if ($this->currentUser->hasPermission('view webprofiler toolbar')) {
      $this->injectToolbar($response);
    }
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Response $response
   */
  protected function injectToolbar(Response $response) {
    $content = $response->getContent();
    $pos = mb_strripos($content, '</body>');

    if (FALSE !== $pos) {
      if ($token = $response->headers->get('X-Debug-Token')) {
        $loader = [
          '#theme' => 'webprofiler_loader',
          '#token' => $token,
          '#profiler_url' => $this->urlGenerator->generate('webprofiler.toolbar', ['profile' => $token]),
        ];

        $content = mb_substr($content, 0, $pos) . $this->renderer->renderRoot($loader) . mb_substr($content, $pos);
        $response->setContent($content);
      }
    }
  }

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', -128],
    ];
  }
}
