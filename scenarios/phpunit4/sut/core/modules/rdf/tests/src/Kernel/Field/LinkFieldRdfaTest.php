<?php

namespace Drupal\Tests\rdf\Kernel\Field;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests the placement of RDFa in link field formatters.
 *
 * @group rdf
 */
class LinkFieldRdfaTest extends FieldRdfaTestBase {

  /**
   * {@inheritdoc}
   */
  protected $fieldType = 'link';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['link', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createTestField();

    // Add the mapping.
    $mapping = rdf_get_mapping('entity_test', 'entity_test');
    $mapping->setFieldMapping($this->fieldName, [
      'properties' => ['schema:link'],
    ])->save();

  }

  /**
   * Tests all formatters with link to external page.
   */
  public function testAllFormattersExternal() {
    // Set up test values.
    $this->testValue = 'http://test.me/foo/bar/neque/porro/quisquam/est/qui-dolorem?path=foo/bar/neque/porro/quisquam/est/qui-dolorem';
    $this->entity = EntityTest::create([]);
    $this->entity->{$this->fieldName}->uri = $this->testValue;

    // Set up the expected result.
    $expected_rdf = [
      'value' => $this->testValue,
      'type' => 'uri',
    ];

    $this->runTestAllFormatters($expected_rdf, 'external');
  }

  /**
   * Tests all formatters with link to internal page.
   */
  public function testAllFormattersInternal() {
    // Set up test values.
    $this->testValue = 'admin';
    $this->entity = EntityTest::create([]);
    $this->entity->{$this->fieldName}->uri = 'internal:/admin';

    // Set up the expected result.
    // AssertFormatterRdfa looks for a full path.
    $expected_rdf = [
      'value' => $this->uri . '/' . $this->testValue,
      'type' => 'uri',
    ];

    $this->runTestAllFormatters($expected_rdf, 'internal');
  }

  /**
   * Tests all formatters with link to frontpage.
   */
  public function testAllFormattersFront() {
    // Set up test values.
    $this->testValue = '/';
    $this->entity = EntityTest::create([]);
    $this->entity->{$this->fieldName}->uri = 'internal:/';

    // Set up the expected result.
    $expected_rdf = [
      'value' => $this->uri . '/',
      'type' => 'uri',
    ];

    $this->runTestAllFormatters($expected_rdf, 'front');
  }

  /**
   * Helper function to test all link formatters.
   */
  public function runTestAllFormatters($expected_rdf, $type = NULL) {

    // Test the link formatter: trim at 80, no other settings.
    $formatter = [
      'type' => 'link',
      'settings' => [
        'trim_length' => 80,
        'url_only' => FALSE,
        'url_plain' => FALSE,
        'rel' => '',
        'target' => '',
      ],
    ];
    $this->assertFormatterRdfa($formatter, 'http://schema.org/link', $expected_rdf);

    // Test the link formatter: trim at 40, nofollow, new window.
    $formatter = [
      'type' => 'link',
      'settings' => [
        'trim_length' => 40,
        'url_only' => FALSE,
        'url_plain' => FALSE,
        'rel' => 'nofollow',
        'target' => '_blank',
      ],
    ];
    $this->assertFormatterRdfa($formatter, 'http://schema.org/link', $expected_rdf);

    // Test the link formatter: trim at 40, URL only (not plaintext) nofollow,
    // new window.
    $formatter = [
      'type' => 'link',
      'settings' => [
        'trim_length' => 40,
        'url_only' => TRUE,
        'url_plain' => FALSE,
        'rel' => 'nofollow',
        'target' => '_blank',
      ],
    ];
    $this->assertFormatterRdfa($formatter, 'http://schema.org/link', $expected_rdf);

    // Test the link_separate formatter: trim at 40, nofollow, new window.
    $formatter = [
      'type' => 'link_separate',
      'settings' => [
        'trim_length' => 40,
        'rel' => 'nofollow',
        'target' => '_blank',
      ],
    ];
    $this->assertFormatterRdfa($formatter, 'http://schema.org/link', $expected_rdf);

    // Change the expected value here to literal. When formatted as plaintext
    // then the RDF is expecting a 'literal' not a 'uri'.
    $expected_rdf = [
      'value' => $this->testValue,
      'type' => 'literal',
    ];
    // Test the link formatter: trim at 20, url only (as plaintext.)
    $formatter = [
      'type' => 'link',
      'settings' => [
        'trim_length' => 20,
        'url_only' => TRUE,
        'url_plain' => TRUE,
        'rel' => '0',
        'target' => '0',
      ],
    ];
    $this->assertFormatterRdfa($formatter, 'http://schema.org/link', $expected_rdf);

    // Test the link formatter: do not trim, url only (as plaintext.)
    $formatter = [
      'type' => 'link',
      'settings' => [
        'trim_length' => 0,
        'url_only' => TRUE,
        'url_plain' => TRUE,
        'rel' => '0',
        'target' => '0',
      ],
    ];
    $this->assertFormatterRdfa($formatter, 'http://schema.org/link', $expected_rdf);
  }

}
