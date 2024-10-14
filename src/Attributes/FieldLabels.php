<?php

namespace Drush\Attributes;

use Attribute;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FieldLabels implements FormatterConfigurationItemProviderInterface
{

    const KEY = 'field-labels';

    /**
     * @param $labels
     *   An associative array of field names and labels for display.
     */
    public function __construct(
        public array $labels
    ) {
    }

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        $args = $attribute->getArguments();
        return [self::KEY => $args['labels']];
    }
}
