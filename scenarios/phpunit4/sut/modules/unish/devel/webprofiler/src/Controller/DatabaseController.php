<?php

namespace Drupal\webprofiler\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\webprofiler\DataCollector\DatabaseDataCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Class DatabaseController
 */
class DatabaseController extends ControllerBase {

  /**
   * @var \Symfony\Component\HttpKernel\Profiler\Profiler
   */
  private $profiler;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('profiler'),
      $container->get('database')
    );
  }

  /**
   * Constructs a new WebprofilerController.
   *
   * @param \Symfony\Component\HttpKernel\Profiler\Profiler $profiler
   * @param \Drupal\Core\Database\Connection $database
   */
  public function __construct(Profiler $profiler, Connection $database) {
    $this->profiler = $profiler;
    $this->database = $database;
  }

  /**
   * @param Profile $profile
   * @param int $qid
   *
   * @return JsonResponse
   */
  public function explainAction(Profile $profile, $qid) {
    $query = $this->getQuery($profile, $qid);

    $data = [];
    $result = $this->database->query('EXPLAIN ' . $query['query'], (array) $query['args'])
      ->fetchAllAssoc('table');
    $i = 1;
    foreach ($result as $row) {
      foreach ($row as $key => $value) {
        $data[$i][$key] = $value;
      }
      $i++;
    }

    return new JsonResponse(['data' => $data]);
  }

  /**
   * @param $profile ->getToken()
   * @param int $qid
   *
   * @return array
   */
  private function getQuery(Profile $profile, $qid) {
    $this->profiler->disable();
    $token = $profile->getToken();

    if (!$profile = $this->profiler->loadProfile($token)) {
      throw new NotFoundHttpException($this->t('Token @token does not exist.', ['@token' => $token]));
    }

    /** @var DatabaseDataCollector $databaseCollector */
    $databaseCollector = $profile->getCollector('database');

    $queries = $databaseCollector->getQueries();
    $query = $queries[$qid];

    return $query;
  }
}
