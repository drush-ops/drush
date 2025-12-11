<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class FieldLabels extends \Consolidation\AnnotatedCommand\Attributes\FieldLabels implements FormatterConfigurationItemProviderInterface
{
    const KEY = FormatterOptions::FIELD_LABELS;

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        $args = $attribute->getArguments();
        return [self::KEY => $args['labels']];
    }
}
