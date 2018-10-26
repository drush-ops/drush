<?php

namespace Drupal\webprofiler\Profiler;

use Drupal\Core\Database\Connection;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;

/**
 * Implements a profiler storage using the DBTNG query api.
 */
class DatabaseProfilerStorage implements ProfilerStorageInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new DatabaseProfilerStorage instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function find($ip, $url, $limit, $method, $start = NULL, $end = NULL) {
    $select = $this->database->select('webprofiler', 'wp', ['fetch' => \PDO::FETCH_ASSOC]);

    if (NULL === $start) {
      $start = 0;
    }

    if (NULL === $end) {
      $end = time();
    }

    if ($ip = preg_replace('/[^\d\.]/', '', $ip)) {
      $select->condition('ip', '%' . $this->database->escapeLike($ip) . '%', 'LIKE');
    }

    if ($url) {
      $select->condition('url', '%' . $this->database->escapeLike(addcslashes($url, '%_\\')) . '%', 'LIKE');
    }

    if ($method) {
      $select->condition('method', $method);
    }

    if (!empty($start)) {
      $select->condition('time', $start, '>=');
    }

    if (!empty($end)) {
      $select->condition('time', $end, '<=');
    }

    $select->fields('wp', [
      'token',
      'ip',
      'method',
      'url',
      'time',
      'parent',
      'status_code'
    ]);
    $select->orderBy('time', 'DESC');
    $select->range(0, $limit);
    return $select->execute()
      ->fetchAllAssoc('token');
  }

  /**
   * {@inheritdoc}
   */
  public function read($token) {
    $record = $this->database->select('webprofiler', 'w')
      ->fields('w')
      ->condition('token', $token)
      ->execute()
      ->fetch();
    if (isset($record->data)) {
      return $this->createProfileFromData($token, $record);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function write(Profile $profile) {
    $args = [
      'token' => $profile->getToken(),
      'parent' => $profile->getParentToken(),
      'data' => base64_encode(serialize($profile->getCollectors())),
      'ip' => $profile->getIp(),
      'method' => $profile->getMethod(),
      'url' => $profile->getUrl(),
      'time' => $profile->getTime(),
      'created_at' => time(),
      'status_code' => $profile->getStatusCode(),
    ];

    try {
      $query = $this->database->select('webprofiler', 'w')
        ->fields('w', ['token']);
      $query->condition('token', $profile->getToken());
      $count = $query->countQuery()->execute()->fetchAssoc();

      if ($count['expression']) {
        $this->database->update('webprofiler')
          ->fields($args)
          ->condition('token', $profile->getToken())
          ->execute();
      }
      else {
        $this->database->insert('webprofiler')->fields($args)->execute();
      }

      $status = TRUE;
    } catch (\Exception $e) {
      $status = FALSE;
    }

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function purge() {
    $this->database->truncate('webprofiler')->execute();
  }

  /**
   * @param string $token
   * @param $data
   *
   * @return Profile
   */
  private function createProfileFromData($token, $data) {
    $profile = new Profile($token);
    $profile->setIp($data->ip);
    $profile->setMethod($data->method);
    $profile->setUrl($data->url);
    $profile->setTime($data->time);
    $profile->setCollectors(unserialize(base64_decode($data->data)));
    $profile->setStatusCode($data->status_code);

    return $profile;
  }
}
