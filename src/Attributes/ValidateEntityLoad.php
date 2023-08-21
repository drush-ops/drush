<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\ValidatorsCommands;

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
        $commandInfo->addAnnotation(ValidatorsCommands::VALIDATE_ENTITY_LOAD, "{$args['entityType']} {$args['argumentName']}");
    }
}
