<?php

namespace Drupal\Tests\datetime\Functional\EntityResource\EntityTest;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\entity_test\Functional\Rest\EntityTestResourceTestBase;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Tests the datetime field constraint with 'datetime' items.
 *
 * @group datetime
 */
class EntityTestDatetimeTest extends EntityTestResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * The ISO date string to use throughout the test.
   *
   * @var string
   */
  protected static $dateString = '2017-03-01T20:02:00';

  /**
   * Datetime test field name.
   *
   * @var string
   */
  protected static $fieldName = 'field_datetime';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['datetime', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Add datetime field.
    FieldStorageConfig::create([
      'field_name' => static::$fieldName,
      'type' => 'datetime',
      'entity_type' => static::$entityTypeId,
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
    ])
      ->save();

    FieldConfig::create([
      'field_name' => static::$fieldName,
      'entity_type' => static::$entityTypeId,
      'bundle' => $this->entity->bundle(),
      'settings' => ['default_value' => static::$dateString],
    ])
      ->save();

    // Reload entity so that it has the new field.
    $this->entity = $this->entityStorage->load($this->entity->id());
    $this->entity->set(static::$fieldName, ['value' => static::$dateString]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $entity_test = EntityTest::create([
      'name' => 'Llama',
      'type' => static::$entityTypeId,
      static::$fieldName => static::$dateString,
    ]);
    $entity_test->setOwnerId(0);
    $entity_test->save();

    return $entity_test;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return parent::getExpectedNormalizedEntity() + [
      static::$fieldName => [
        [
          'value' => $this->entity->get(static::$fieldName)->value,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return parent::getNormalizedPostEntity() + [
      static::$fieldName => [
        [
          'value' => static::$dateString,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options) {
    parent::assertNormalizationEdgeCases($method, $url, $request_options);

    if ($this->entity->getEntityType()->hasKey('bundle')) {
      $fieldName = static::$fieldName;

      // DX: 422 when date type is incorrect.
      $normalization = $this->getNormalizedPostEntity();
      $normalization[static::$fieldName][0]['value'] = [
        '2017', '03', '01', '21', '53', '00',
      ];

      $request_options[RequestOptions::BODY] = $this->serializer->encode($normalization, static::$format);
      $response = $this->request($method, $url, $request_options);
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The datetime value must be a string.\n{$fieldName}.0.value: This value should be of the correct primitive type.\n";
      $this->assertResourceErrorResponse(422, $message, $response);

      // DX: 422 when date format is incorrect.
      $normalization = $this->getNormalizedPostEntity();
      $value = '2017-03-01';
      $normalization[static::$fieldName][0]['value'] = $value;

      $request_options[RequestOptions::BODY] = $this->serializer->encode($normalization, static::$format);
      $response = $this->request($method, $url, $request_options);
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The datetime value '{$value}' is invalid for the format 'Y-m-d\\TH:i:s'\n";
      $this->assertResourceErrorResponse(422, $message, $response);

      // DX: 422 when date format is incorrect.
      $normalization = $this->getNormalizedPostEntity();
      $value = '2017-13-55T20:02:00';
      $normalization[static::$fieldName][0]['value'] = $value;

      $request_options[RequestOptions::BODY] = $this->serializer->encode($normalization, static::$format);
      $response = $this->request($method, $url, $request_options);
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The datetime value '{$value}' did not parse properly for the format 'Y-m-d\\TH:i:s'\n{$fieldName}.0.value: This value should be of the correct primitive type.\n";
      $this->assertResourceErrorResponse(422, $message, $response);
    }
  }

}
