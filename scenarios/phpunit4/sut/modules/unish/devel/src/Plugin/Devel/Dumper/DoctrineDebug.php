<?php

namespace Drupal\devel\Plugin\Devel\Dumper;

use Doctrine\Common\Util\Debug;
use Drupal\Component\Utility\Xss;
use Drupal\devel\DevelDumperBase;

/**
 * Provides a DoctrineDebug dumper plugin.
 *
 * @DevelDumper(
 *   id = "default",
 *   label = @Translation("Default"),
 *   description = @Translation("Wrapper for <a href='http://www.doctrine-project.org/api/common/2.3/class-Doctrine.Common.Util.Debug.html'>Doctrine</a> debugging tool.")
 * )
 */
class DoctrineDebug extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    $name = $name ? $name . ' => ' : '';
    $variable = Debug::export($input, 6);

    ob_start();
    print_r($variable);
    $dump = ob_get_contents();
    ob_end_clean();

    // Run Xss::filterAdmin on the resulting string to prevent
    // cross-site-scripting (XSS) vulnerabilities.
    $dump = Xss::filterAdmin($dump);

    $dump = '<pre>' . $name . $dump . '</pre>';

    return $this->setSafeMarkup($dump);
  }

  /**
   * {@inheritdoc}
   */
  public function exportAsRenderable($input, $name = NULL) {
    $output['container'] = [
      '#type' => 'details',
      '#title' => $name ? : $this->t('Variable'),
      '#attached' => [
        'library' => ['devel/devel']
      ],
      '#attributes' => [
        'class' => ['container-inline', 'devel-dumper', 'devel-selectable'],
      ],
      'export' => [
        '#markup' => $this->export($input),
      ],
    ];

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return TRUE;
  }

}
