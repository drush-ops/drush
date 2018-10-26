<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class DatabaseDataCollector
 */
class DatabaseDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory) {
    $this->database = $database;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $connections = [];
    foreach (Database::getAllConnectionInfo() as $key => $info) {
      $database = Database::getConnection('default', $key);
      $connections[$key] = $database->getLogger()->get('webprofiler');
    }

    $this->data['connections'] = array_keys($connections);

    $data = [];
    foreach ($connections as $key => $queries) {
      foreach ($queries as $query) {
        // Remove caller args.
        unset($query['caller']['args']);

        // Remove query args element if empty.
        if (isset($query['args']) && empty($query['args'])) {
          unset($query['args']);
        }

        // Save time in milliseconds.
        $query['time'] = $query['time'] * 1000;
        $query['database'] = $key;
        $data[] = $query;
      }
    }

    $querySort = $this->configFactory->get('webprofiler.config')
      ->get('query_sort');
    if ('duration' === $querySort) {
      usort(
        $data, [
          "Drupal\\webprofiler\\DataCollector\\DatabaseDataCollector",
          "orderQueryByTime",
        ]
      );
    }

    $this->data['queries'] = $data;

    $options = $this->database->getConnectionOptions();

    // Remove password for security.
    unset($options['password']);

    $this->data['database'] = $options;
  }

  /**
   * @return array
   */
  public function getDatabase() {
    return $this->data['database'];
  }

  /**
   * @return int
   */
  public function getQueryCount() {
    return count($this->data['queries']);
  }

  /**
   * @return array
   */
  public function getQueries() {
    return $this->data['queries'];
  }

  /**
   * Returns the total execution time.
   *
   * @return float
   */
  public function getTime() {
    $time = 0;

    foreach ($this->data['queries'] as $query) {
      $time += $query['time'];
    }

    return $time;
  }

  /**
   * Returns a color based on the number of executed queries.
   *
   * @return string
   */
  public function getColorCode() {
    if ($this->getQueryCount() < 100) {
      return 'green';
    }
    if ($this->getQueryCount() < 200) {
      return 'yellow';
    }

    return 'red';
  }

  /**
   * Returns the configured query highlight threshold.
   *
   * @return int
   */
  public function getQueryHighlightThreshold() {
    // When a profile is loaded from storage this object is deserialized and
    // no constructor is called so we cannot use dependency injection.
    return \Drupal::config('webprofiler.config')->get('query_highlight');
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'database';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Database');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Executed queries: @count', ['@count' => $this->getQueryCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAcCAYAAABh2p9gAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAQRJREFUeNpi/P//PwM1ARMDlcGogZQDlpMnT7pxc3NbA9nhQKxOpL5rQLwJiPeBsI6Ozl+YBOOOHTv+AOllQNwtLS39F2owKYZ/gRq8G4i3ggxEToggWzvc3d2Pk+1lNL4fFAs6ODi8JzdS7mMRVyDVoAMHDsANdAPiOCC+jCQvQKqBQB/BDbwBxK5AHA3E/kB8nKJkA8TMQBwLxaBIKQbi70AvTADSBiSadwFXpCikpKQU8PDwkGTaly9fHFigkaKIJid4584dkiMFFI6jkTJII0WVmpHCAixZQEXWYhDeuXMnyLsVlEQKI45qFBQZ8eRECi4DBaAlDqle/8A48ip6gAADANdQY88Uc0oGAAAAAElFTkSuQmCC';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries() {
    return [
      'webprofiler/database',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $data = $this->data;

    $conn = Database::getConnection();
    foreach ($data['queries'] as &$query) {
      $explain = TRUE;
      $type = 'select';

      if (strpos($query['query'], 'INSERT') !== FALSE) {
        $explain = FALSE;
        $type = 'insert';
      }

      if (strpos($query['query'], 'UPDATE') !== FALSE) {
        $explain = FALSE;
        $type = 'update';
      }

      if (strpos($query['query'], 'CREATE') !== FALSE) {
        $explain = FALSE;
        $type = 'create';
      }

      if (strpos($query['query'], 'DELETE') !== FALSE) {
        $explain = FALSE;
        $type = 'delete';
      }

      $query['explain'] = $explain;
      $query['type'] = $type;

      $quoted = [];

      if (isset($query['args'])) {
        foreach ((array) $query['args'] as $key => $val) {
          $quoted[$key] = is_null($val) ? 'NULL' : $conn->quote($val);
        }
      }

      $query['query_args'] = strtr($query['query'], $quoted);
    }

    $data['query_highlight_threshold'] = $this->getQueryHighlightThreshold();

    return $data;
  }

  /**
   * @param $a
   * @param $b
   *
   * @return int
   */
  private function orderQueryByTime($a, $b) {
    $at = $a['time'];
    $bt = $b['time'];

    if ($at == $bt) {
      return 0;
    }
    return ($at < $bt) ? 1 : -1;
  }
}
