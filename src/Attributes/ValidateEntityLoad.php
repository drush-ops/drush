<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drush\Utils\StringUtils;

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
        $names = StringUtils::csvToArray($commandData->input()->getArgument($instance->argumentName));
        $loaded = \Drupal::entityTypeManager()->getStorage($instance->entityType)->loadMultiple($names);
        if ($missing = array_diff($names, array_keys($loaded))) {
            $msg = dt('Unable to load the !type: !str', ['!type' => $instance->entityType, '!str' => implode(', ', $missing)]);
            return new CommandError($msg);
        }
    }
}
