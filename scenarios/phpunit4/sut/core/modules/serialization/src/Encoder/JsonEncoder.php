<?php

namespace Drupal\serialization\Encoder;

use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder as BaseJsonEncoder;

/**
 * Adds 'ajax to the supported content types of the JSON encoder'
 *
 * @internal
 *   This encoder should not be used directly. Rather, use the `serializer`
 *   service.
 */
class JsonEncoder extends BaseJsonEncoder implements EncoderInterface, DecoderInterface {

  /**
   * The formats that this Encoder supports.
   *
   * @var array
   */
  protected static $format = ['json', 'ajax'];

  /**
   * {@inheritdoc}
   */
  public function __construct(JsonEncode $encodingImpl = NULL, JsonDecode $decodingImpl = NULL) {
    // Encode <, >, ', &, and " for RFC4627-compliant JSON, which may also be
    // embedded into HTML.
    // @see \Symfony\Component\HttpFoundation\JsonResponse
    $json_encoding_options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    $this->encodingImpl = $encodingImpl ?: new JsonEncode($json_encoding_options);
    $this->decodingImpl = $decodingImpl ?: new JsonDecode(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return in_array($format, static::$format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return in_array($format, static::$format);
  }

}
