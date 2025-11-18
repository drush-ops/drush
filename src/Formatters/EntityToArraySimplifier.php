<?php

declare(strict_types=1);

namespace Drush\Formatters;

use Drupal\Core\Entity\EntityInterface;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\Transformations\SimplifyToArrayInterface;

/**
 * Simplify a EntityInterface to an array.
 */
class EntityToArraySimplifier implements SimplifyToArrayInterface
{
    public function __construct()
    {
    }

    public function canSimplify(\ReflectionClass $dataType): bool
    {
        return interface_exists(EntityInterface::class, false) && $dataType->implementsInterface(EntityInterface::class);
    }

    public function simplifyToArray($structuredData, FormatterOptions $options): array
    {
        return $structuredData->toArray();
    }
}
