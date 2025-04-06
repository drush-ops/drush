<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Toggle between twig debug and caching aggregation.
 */
final class CachingAggregationCommands extends DrushCommands {

  const AGGREGATE = 'caching:aggregation';

  public function __construct(
    private KeyValueFactoryInterface $keyValueFactory,
    private ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Enables or disables CSS/JS aggregation and Twig debugging.
   *
   * @param string $mode
   *   Accepts "enable" or "disable".
   */
  #[CLI\Command(name: self::AGGREGATE, aliases: ['cag', 'caching-aggregation'])]
  #[CLI\Argument(name: 'mode', description: '"enable" or "disable"')]
  #[CLI\Usage(name: 'drush caching-aggregation enable', description: 'Enables CSS/JS aggregation and disables Twig debugging.')]
  #[CLI\Usage(name: 'drush caching-aggregation disable', description: 'Disables CSS/JS aggregation and enables Twig debugging.')]
  public function toggleAggregation(string $mode): void {
    if (!in_array($mode, ['enable', 'disable'])) {
      $this->logger()->error("Invalid mode. Use 'enable' or 'disable'.");
      return;
    }

    $aggregation_mode = $mode === 'enable';

    $this->keyValueFactory->get('development_settings')->setMultiple([
      'disable_rendered_output_cache_bins' => !$aggregation_mode,
      'twig_debug' => !$aggregation_mode,
      'twig_cache_disable' => !$aggregation_mode,
    ]);

    $this->configFactory->getEditable('system.performance')
      ->set('css.preprocess', $aggregation_mode)
      ->set('js.preprocess', $aggregation_mode)
      ->save();

    drupal_flush_all_caches();

    $this->logger()->success(sprintf(
      'CSS/JS aggregation %s, Twig debugging %s.',
      $aggregation_mode ? 'enabled' : 'disabled',
      $aggregation_mode ? 'disabled' : 'enabled'
    ));
  }

}
