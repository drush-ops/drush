<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateEntityLoad
{
    /**
     * @param $entityType
     *   The type of entity.
     * @param string $argumentName
     *   The name of the argument which specifies the entity ID.
     */
    public function __construct(
        public string $entityType,
        public string $argumentName
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('validate-entity-load', "{$args['entityType']} {$args['argumentName']}");
    }
}
