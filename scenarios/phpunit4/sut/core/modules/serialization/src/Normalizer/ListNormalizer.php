<?php

namespace Drupal\serialization\Normalizer;

/**
 * Converts list objects to arrays.
 *
 * Ordinarily, this would be handled automatically by Serializer, but since
 * there is a TypedDataNormalizer and the Field class extends TypedData, any
 * Field will be handled by that Normalizer instead of being traversed. This
 * class ensures that TypedData classes that also implement ListInterface are
 * traversed instead of simply returning getValue().
 */
class ListNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\TypedData\ListInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $attributes = [];
    foreach ($object as $fieldItem) {
      $attributes[] = $this->serializer->normalize($fieldItem, $format, $context);
    }
    return $attributes;
  }

}
