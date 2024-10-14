<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DefaultTableFields implements FormatterConfigurationItemProviderInterface
{
    const KEY = 'default-table-fields';

    /**
     * @param $fields
     *   An array of field names to show by default when using table formatter.
     */
    public function __construct(public array $fields)
    {
    }

    public function getConfigurationItem(\ReflectionAttribute $attribute): array
    {
        $args = $attribute->getArguments();
        return [self::KEY => $args['fields']];
    }
}
