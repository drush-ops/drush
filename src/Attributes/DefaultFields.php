<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class DefaultFields extends \Consolidation\AnnotatedCommand\Attributes\DefaultFields implements FormatterConfigurationItemProviderInterface
{
    const KEY = 'default-fields';

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        $args = $attribute->getArguments();
        return [self::KEY => $args['fields']];
    }
}
