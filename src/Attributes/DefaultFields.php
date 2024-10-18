<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class DefaultFields
{
    const KEY = 'default-fields';

    /**
     * @param $fields
     *   An array of field names to show by default.
     */
    public function __construct(public array $fields)
    {
    }

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation('default-fields', $args['fields']);
    }
}
