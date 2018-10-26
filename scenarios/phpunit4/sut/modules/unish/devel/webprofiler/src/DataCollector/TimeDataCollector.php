<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector as BaseTimeDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Class TimeDataCollector.
 */
class TimeDataCollector extends BaseTimeDataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
   * @param $stopwatch
   */
  public function __construct(KernelInterface $kernel = NULL, $stopwatch = NULL) {
    parent::__construct($kernel, $stopwatch);
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    parent::collect($request, $response, $exception);

    $this->data['memory_limit'] = $this->convertToBytes(ini_get('memory_limit'));
    $this->updateMemoryUsage();
  }

  /**
   * {@inheritdoc}
   */
  public function lateCollect() {
    parent::lateCollect();

    $this->updateMemoryUsage();
  }

  /**
   * Gets the memory.
   *
   * @return int
   *   The memory
   */
  public function getMemory() {
    return $this->data['memory'];
  }

  /**
   * Gets the PHP memory limit.
   *
   * @return int
   *   The memory limit
   */
  public function getMemoryLimit() {
    return $this->data['memory_limit'];
  }

  /**
   * Updates the memory usage data.
   */
  public function updateMemoryUsage() {
    $this->data['memory'] = memory_get_peak_usage(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Timeline');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Duration: @duration', ['@duration' => sprintf('%.0f ms', $this->getDuration())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAcCAYAAABoMT8aAAABqUlEQVR42t2Vv0sCYRyHX9OmEhsMx/YKGlwLQ69DTEUSBJEQEy5J3FRc/BsuiFqEIIcQIRo6ysUhoaBBWhoaGoJwiMJLglRKrs8bXgienmkQdPDAwX2f57j3fhFJkkbiPwTK5bIiFoul3kmPud8MqKMewDXpwuGww+12n9hsNhFnlijYf/Z4PDmO45Yxo+10ZFGTyWRMEItU6AdCx7lczkgd6n7J2Wx2xm63P6jJMk6n80YQBBN1aUDv9XqvlAbbm2LE7/cLODRB0un0VveAeoDC8/waCQQC18MGQqHQOcEKvw8bcLlcL6TfYnVtCrGRAlartUUYhmn1jKg/E3USjUYfhw3E4/F7ks/nz4YNFIvFQ/ogbUYikdefyqlU6gnuOg2YK5XKvs/n+xhUDgaDTVEUt+HO04ABOBA5isViDTU5kUi81Wq1AzhWMEkDGmAEq2C3UCjcYXGauDvfEsuyUjKZbJRKpVvM8IABU9SVX+cxYABmwIE9cFqtVi9xtgvsC2AHbIAFoKey0gdlHEyDObAEWLACFsEsMALdIJ80+dK0bTS95v7+v/AJnis0eO906QwAAAAASUVORK5CYII=';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries() {
    return [
      'webprofiler/timeline',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalSettings() {
    /** @var StopwatchEvent[] $collectedEvents */
    $collectedEvents = $this->getEvents();

    if (!empty($collectedEvents)) {
      $sectionPeriods = $collectedEvents['__section__']->getPeriods();
      $endTime = end($sectionPeriods)->getEndTime();
      $events = [];

      foreach ($collectedEvents as $key => $collectedEvent) {
        if ('__section__' != $key) {
          $periods = [];
          foreach ($collectedEvent->getPeriods() as $period) {
            $periods[] = [
              'start' => sprintf("%F", $period->getStartTime()),
              'end' => sprintf("%F", $period->getEndTime()),
            ];
          }

          $events[] = [
            "name" => $key,
            "category" => $collectedEvent->getCategory(),
            "origin" => sprintf("%F", $collectedEvent->getOrigin()),
            "starttime" => sprintf("%F", $collectedEvent->getStartTime()),
            "endtime" => sprintf("%F", $collectedEvent->getEndTime()),
            "duration" => sprintf("%F", $collectedEvent->getDuration()),
            "memory" => sprintf("%.1F", $collectedEvent->getMemory() / 1024 / 1024),
            "periods" => $periods,
          ];
        }
      }

      return ['time' => ['events' => $events, 'endtime' => $endTime]];
    }
    else {
      return ['time' => ['events' => [], 'endtime' => 0]];
    }
  }

  /**
   * @return array
   */
  public function getData() {
    $data = $this->data;

    $data['duration'] = $this->getDuration();
    $data['initTime'] = $this->getInitTime();

    return $data;
  }
}
