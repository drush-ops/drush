<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\AttributeInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidatePermissions implements AttributeInterface
{
    /**
     * @param $argName
     *   The argument name containing the required permissions.
     */
    public function __construct(
        public array $argName,
    ) {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('validate-permissions', $args['argName']);
    }
}
