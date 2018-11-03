<?php

namespace Drupal\views_test_modal\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class TestController extends ControllerBase {

  /**
   * Renders a link to open the /admin/content view in a modal dialog.
   */
  public function modal() {
    $build = [];

    $build['open_admin_content'] = [
      '#type' => 'link',
      '#title' => $this->t('Administer content'),
      '#url' => Url::fromUserInput('/admin/content'),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'dialogClass' => 'views-test-modal',
          'height' => '50%',
          'width' => '50%',
          'title' => $this->t('Administer content'),
        ]),
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    return $build;
  }

}
