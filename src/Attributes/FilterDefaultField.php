<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FilterDefaultField
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

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('filter-default-field', $args['field']);
    }
}
