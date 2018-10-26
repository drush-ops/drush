<?php

namespace Drupal\kint\Plugin\Devel\Dumper;

use Drupal\devel\DevelDumperBase;

/**
 * Provides a Kint dumper plugin.
 *
 * @DevelDumper(
 *   id = "kint",
 *   label = @Translation("Kint"),
 *   description = @Translation("Wrapper for <a href='https://github.com/raveren/kint'>Kint</a> debugging tool."),
 * )
 */
class Kint extends DevelDumperBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // @TODO find another solution for kint class inclusion!
    kint_require();
  }

  /**
   * {@inheritdoc}
   */
  public function export($input, $name = NULL) {
    ob_start();
    \Kint::dump($input);
    $dump = ob_get_clean();

    // Kint does't allow to assign a title to the dump. Workaround to use the
    // passed in name as dump title.
    if ($name) {
      $dump = preg_replace('/\$input/', $name, $dump, 1);
    }

    return $this->setSafeMarkup($dump);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkRequirements() {
    return kint_require();
  }

}
