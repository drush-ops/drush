<?php

namespace Drupal\Tests\filter\Functional\Rest;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;

abstract class FilterFormatResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'filter_format';

  /**
   * @var \Drupal\filter\FilterFormatInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer filters']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $pablo_format = FilterFormat::create([
      'name' => 'Pablo Piccasso',
      'format' => 'pablo',
      'langcode' => 'es',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<p> <a> <b> <lo>',
          ],
        ],
      ],
    ]);
    $pablo_format->save();
    return $pablo_format;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return [
      'dependencies' => [],
      'filters' => [
        'filter_html' => [
          'id' => 'filter_html',
          'provider' => 'filter',
          'status' => TRUE,
          'weight' => -10,
          'settings' => [
            'allowed_html' => '<p> <a> <b> <lo>',
            'filter_html_help' => TRUE,
            'filter_html_nofollow' => FALSE,
          ],
        ],
      ],
      'format' => 'pablo',
      'langcode' => 'es',
      'name' => 'Pablo Piccasso',
      'status' => TRUE,
      'uuid' => $this->entity->uuid(),
      'weight' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

}
