<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DefaultFields implements FormatterConfigurationItemProviderInterface
{
    const KEY = 'default-fields';

    /**
     * @param $fields
     *   An array of field names to show by default.
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
