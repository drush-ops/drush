<?php

namespace Drupal\Core\Render\Element;

/**
 * Provides a link render element for a "more" link, like those used in blocks.
 *
 * Properties:
 * - #title: The text of the link to generate (defaults to 'More').
 *
 * See \Drupal\Core\Render\Element\Link for additional properties.
 *
 * Usage Example:
 * @code
 * $build['more'] = [
 *   '#type' => 'more_link',
 *   '#url' => Url::fromRoute('examples.more_examples')
 * ]
 * @endcode
 *
 * @RenderElement("more_link")
 */
class MoreLink extends Link {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    return [
      '#title' => $this->t('More'),
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => ['class' => ['more-link']],
        ],
      ],
    ] + $info;
  }

}
