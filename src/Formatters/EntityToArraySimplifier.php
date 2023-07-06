<?php

declare(strict_types=1);

namespace Drush\Formatters;

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
        return interface_exists('Drupal\Core\Entity\EntityInterface', false) && $dataType->implementsInterface('\Drupal\Core\Entity\EntityInterface');
    }

    public function simplifyToArray($structuredData, FormatterOptions $options): array
    {
        return $structuredData->toArray();
    }
}
