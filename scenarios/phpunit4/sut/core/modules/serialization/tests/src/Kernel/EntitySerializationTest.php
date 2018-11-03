<?php

namespace Drupal\Tests\serialization\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;

/**
 * Tests that entities can be serialized to supported core formats.
 *
 * @group serialization
 */
class EntitySerializationTest extends NormalizerTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['serialization', 'system', 'field', 'entity_test', 'text', 'filter', 'user', 'entity_serialization_test'];

  /**
   * The test values.
   *
   * @var array
   */
  protected $values;

  /**
   * The test entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The class name of the test class.
   *
   * @var string
   */
  protected $entityClass = 'Drupal\entity_test\Entity\EntityTest';

  protected function setUp() {
    parent::setUp();

    // User create needs sequence table.
    $this->installSchema('system', ['sequences']);

    FilterFormat::create([
      'format' => 'my_text_format',
      'name' => 'My Text Format',
      'filters' => [
        'filter_html' => [
          'module' => 'filter',
          'status' => TRUE,
          'weight' => 10,
          'settings' => [
            'allowed_html' => '<p>',
          ],
        ],
        'filter_autop' => [
          'module' => 'filter',
          'status' => TRUE,
          'weight' => 10,
          'settings' => [],
        ],
      ],
    ])->save();

    // Create a test user to use as the entity owner.
    $this->user = \Drupal::entityManager()->getStorage('user')->create([
      'name' => 'serialization_test_user',
      'mail' => 'foo@example.com',
      'pass' => '123456',
    ]);
    $this->user->save();

    // Create a test entity to serialize.
    $test_text_value = $this->randomMachineName();
    $this->values = [
      'name' => $this->randomMachineName(),
      'user_id' => $this->user->id(),
      'field_test_text' => [
        'value' => $test_text_value,
        'format' => 'my_text_format',
      ],
    ];
    $this->entity = EntityTestMulRev::create($this->values);
    $this->entity->save();

    $this->serializer = $this->container->get('serializer');

    $this->installConfig(['field']);
  }

  /**
   * Test the normalize function.
   */
  public function testNormalize() {
    $expected = [
      'id' => [
        ['value' => 1],
      ],
      'uuid' => [
        ['value' => $this->entity->uuid()],
      ],
      'langcode' => [
        ['value' => 'en'],
      ],
      'name' => [
        ['value' => $this->values['name']],
      ],
      'type' => [
        ['value' => 'entity_test_mulrev'],
      ],
      'created' => [
        $this->formatExpectedTimestampItemValues($this->entity->created->value),
      ],
      'user_id' => [
        [
          // id() will return the string value as it comes from the database.
          'target_id' => (int) $this->user->id(),
          'target_type' => $this->user->getEntityTypeId(),
          'target_uuid' => $this->user->uuid(),
          'url' => $this->user->url(),
        ],
      ],
      'revision_id' => [
        ['value' => 1],
      ],
      'default_langcode' => [
        ['value' => TRUE],
      ],
      'revision_translation_affected' => [
        ['value' => TRUE],
      ],
      'non_rev_field' => [],
      'non_mul_field' => [],
      'field_test_text' => [
        [
          'value' => $this->values['field_test_text']['value'],
          'format' => $this->values['field_test_text']['format'],
          'processed' => "<p>{$this->values['field_test_text']['value']}</p>",
        ],
      ],
    ];

    $normalized = $this->serializer->normalize($this->entity);

    foreach (array_keys($expected) as $fieldName) {
      $this->assertSame($expected[$fieldName], $normalized[$fieldName], "Normalization produces expected array for $fieldName.");
    }
    $this->assertEqual(array_diff_key($normalized, $expected), [], 'No unexpected data is added to the normalized array.');
  }

  /**
   * Tests user normalization, using the entity_serialization_test module to
   * override some default access controls.
   */
  public function testUserNormalize() {
    // Test password isn't available.
    $normalized = $this->serializer->normalize($this->user);

    $this->assertFalse(array_key_exists('pass', $normalized), '"pass" key does not exist in normalized user');
    $this->assertFalse(array_key_exists('mail', $normalized), '"mail" key does not exist in normalized user');

    // Test again using our test user, so that our access control override will
    // allow password viewing.
    $normalized = $this->serializer->normalize($this->user, NULL, ['account' => $this->user]);

    // The key 'pass' will now exist, but the password value should be
    // normalized to NULL.
    $this->assertIdentical($normalized['pass'], [NULL], '"pass" value is normalized to [NULL]');
  }

  /**
   * Test registered Serializer's entity serialization for core's formats.
   */
  public function testSerialize() {
    // Test that Serializer responds using the ComplexDataNormalizer and
    // JsonEncoder. The output of ComplexDataNormalizer::normalize() is tested
    // elsewhere, so we can just assume that it works properly here.
    $normalized = $this->serializer->normalize($this->entity, 'json');
    $expected = Json::encode($normalized);
    // Test 'json'.
    $actual = $this->serializer->serialize($this->entity, 'json');
    $this->assertIdentical($actual, $expected, 'Entity serializes to JSON when "json" is requested.');
    $actual = $this->serializer->serialize($normalized, 'json');
    $this->assertIdentical($actual, $expected, 'A normalized array serializes to JSON when "json" is requested');
    // Test 'ajax'.
    $actual = $this->serializer->serialize($this->entity, 'ajax');
    $this->assertIdentical($actual, $expected, 'Entity serializes to JSON when "ajax" is requested.');
    $actual = $this->serializer->serialize($normalized, 'ajax');
    $this->assertIdentical($actual, $expected, 'A normalized array serializes to JSON when "ajax" is requested');

    // Generate the expected xml in a way that allows changes to entity property
    // order.
    $expected_created = $this->formatExpectedTimestampItemValues($this->entity->created->value);

    $expected = [
      'id' => '<id><value>' . $this->entity->id() . '</value></id>',
      'uuid' => '<uuid><value>' . $this->entity->uuid() . '</value></uuid>',
      'langcode' => '<langcode><value>en</value></langcode>',
      'name' => '<name><value>' . $this->values['name'] . '</value></name>',
      'type' => '<type><value>entity_test_mulrev</value></type>',
      'created' => '<created><value>' . $expected_created['value'] . '</value><format>' . $expected_created['format'] . '</format></created>',
      'user_id' => '<user_id><target_id>' . $this->user->id() . '</target_id><target_type>' . $this->user->getEntityTypeId() . '</target_type><target_uuid>' . $this->user->uuid() . '</target_uuid><url>' . $this->user->url() . '</url></user_id>',
      'revision_id' => '<revision_id><value>' . $this->entity->getRevisionId() . '</value></revision_id>',
      'default_langcode' => '<default_langcode><value>1</value></default_langcode>',
      'revision_translation_affected' => '<revision_translation_affected><value>1</value></revision_translation_affected>',
      'non_mul_field' => '<non_mul_field/>',
      'non_rev_field' => '<non_rev_field/>',
      'field_test_text' => '<field_test_text><value>' . $this->values['field_test_text']['value'] . '</value><format>' . $this->values['field_test_text']['format'] . '</format><processed><![CDATA[<p>' . $this->values['field_test_text']['value'] . '</p>]]></processed></field_test_text>',
    ];
    // Sort it in the same order as normalised.
    $expected = array_merge($normalized, $expected);
    // Add header and footer.
    array_unshift($expected, '<?xml version="1.0"?>' . PHP_EOL . '<response>');
    $expected[] = '</response>' . PHP_EOL;
    // Reduced the array to a string.
    $expected = implode('', $expected);
    // Test 'xml'. The output should match that of Symfony's XmlEncoder.
    $actual = $this->serializer->serialize($this->entity, 'xml');
    $this->assertIdentical($actual, $expected);
    $actual = $this->serializer->serialize($normalized, 'xml');
    $this->assertIdentical($actual, $expected);
  }

  /**
   * Tests denormalization of an entity.
   */
  public function testDenormalize() {
    $normalized = $this->serializer->normalize($this->entity);

    foreach (['json', 'xml'] as $type) {
      $denormalized = $this->serializer->denormalize($normalized, $this->entityClass, $type, ['entity_type' => 'entity_test_mulrev']);
      $this->assertTrue($denormalized instanceof $this->entityClass, new FormattableMarkup('Denormalized entity is an instance of @class', ['@class' => $this->entityClass]));
      $this->assertIdentical($denormalized->getEntityTypeId(), $this->entity->getEntityTypeId(), 'Expected entity type found.');
      $this->assertIdentical($denormalized->bundle(), $this->entity->bundle(), 'Expected entity bundle found.');
      $this->assertIdentical($denormalized->uuid(), $this->entity->uuid(), 'Expected entity UUID found.');
    }
  }

}
