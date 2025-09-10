<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class DefaultTableFields extends \Consolidation\AnnotatedCommand\Attributes\DefaultTableFields implements FormatterConfigurationItemProviderInterface
{
    const KEY = 'default-table-fields';

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        $args = $attribute->getArguments();
        return [self::KEY => $args['fields']];
    }
}
