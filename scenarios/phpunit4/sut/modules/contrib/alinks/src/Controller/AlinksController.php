<?php

namespace Drupal\alinks\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AlinksController.
 *
 * @package Drupal\alinks\Controller
 */
class AlinksController extends ControllerBase {

  protected $requestStack;

  /**
   * AlinksController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(RequestStack $requestStack, ConfigFactoryInterface $configFactory) {

    $this->requestStack = $requestStack;

    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('config.factory')
    );
  }

  /**
   * Deletes a AccessToken.
   *
   * @return RedirectResponse
   *   Returns to previous page.
   */
  public function delete($id) {

    $displays = $this->configFactory->getEditable('alinks.settings')
      ->get('displays');

    unset($displays[$id]);

    $this->configFactory->getEditable('alinks.settings')
      ->set('displays', $displays)
      ->save();

    $previousUrl = $this->requestStack->getCurrentRequest()->server->get('HTTP_REFERER');

    return new RedirectResponse($previousUrl);
  }

}
