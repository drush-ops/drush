<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

#[Attribute(Attribute::TARGET_METHOD)]
class ValidateEntityLoad extends ValidatorBase
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

    public static function validate(CommandData $commandData, \ReflectionAttribute $attribute)
    {
        $instance = $attribute->newInstance();
        $entityId = $commandData->input()->getArgument($instance->argumentName);
        $entity = \Drupal::entityTypeManager()->getStorage($instance->entityType)->load($entityId);
        if (!$entity) {
            $msg = dt('Entity !type with ID !id does not exist', ['!type' => $instance->entityType, '!id' => $entityId]);
            return new CommandError($msg);
        }
    }
}
