<?php

namespace Drush\Attributes;

use Attribute;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FilterDefaultField implements FormatterConfigurationItemProviderInterface
{
    const KEY = 'filter-default-field';

    /**
     * @param $field
     *   A field name to filter on by default.
     */
    public function __construct(
        public string $field
    ) {
    }

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        $args = $attribute->getArguments();
        return [self::KEY => $args['field']];
    }
}
